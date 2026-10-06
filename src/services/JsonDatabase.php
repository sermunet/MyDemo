<?php

/**
 * Base de datos NoSQL documental escrita en PHP puro (AGENTS.md §6).
 *
 * Almacenamiento: un fichero JSON por documento →  data/<coleccion>/<id>.json
 *
 * - Escritura atómica: fichero temporal + rename().
 * - Concurrencia: bloqueo flock (compartido en lecturas, exclusivo en escrituras).
 * - Transacciones: diario de cambios (journal) con rollback de lo escrito.
 *
 * Sin dependencias externas: no usa MySQL, PDO, SQL ni extensiones adicionales.
 */
final class JsonDatabase
{
    private string $root;

    /** @var resource|null Manejador del fichero de bloqueo (data/.lock). */
    private $lockHandle = null;

    private int $lockDepth = 0;
    private bool $lockIsExclusive = false;

    /** @var array<string, string|null>|null Contenido original de cada fichero tocado en la transacción activa. */
    private ?array $journal = null;

    public function __construct(string $root)
    {
        $root = rtrim($root, '/\\');

        if ($root === '') {
            throw new InvalidArgumentException('La ruta de datos no puede estar vacía.');
        }

        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('No se pudo crear el directorio de datos: ' . $root);
        }

        if (!is_writable($root)) {
            throw new RuntimeException(
                'El directorio de datos no admite escritura: ' . $root
                . '. Comprueba los permisos del usuario que ejecuta PHP.'
            );
        }

        $this->root = $root;
    }

    /** Directorio raíz de la base de datos. */
    public function root(): string
    {
        return $this->root;
    }

    /** Objeto para trabajar con una colección (valida el nombre). */
    public function collection(string $name): JsonCollection
    {
        return new JsonCollection($this, $name);
    }

    /** Nombres de las colecciones existentes (carpetas dentro de data/). */
    public function collectionNames(): array
    {
        $names = [];

        foreach (scandir($this->root) ?: [] as $entry) {
            if ($entry === '' || $entry[0] === '.') {
                continue;
            }

            if (is_dir($this->root . '/' . $entry)) {
                $names[] = $entry;
            }
        }

        sort($names);

        return $names;
    }

    /** Nº de documentos por colección → ['especies' => 7, ...]. */
    public function stats(): array
    {
        $stats = [];

        foreach ($this->collectionNames() as $name) {
            $stats[$name] = count(glob($this->root . '/' . $name . '/*.json') ?: []);
        }

        return $stats;
    }

    /** Crea una carpeta si no existe. Uso interno: la ruta debe venir de JsonCollection. */
    public function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la colección: ' . $dir);
        }
    }

    /**
     * Bloquea la base de datos (reentrante): compartido en lecturas y
     * exclusivo en escrituras, para que nadie escriba mientras leemos.
     */
    public function lock(bool $exclusive): void
    {
        if ($this->lockHandle === null) {
            $handle = @fopen($this->root . '/.lock', 'c');

            if ($handle === false) {
                throw new RuntimeException('No se pudo abrir el fichero de bloqueo en ' . $this->root);
            }

            $this->lockHandle = $handle;
        }

        if ($this->lockDepth === 0) {
            if (!flock($this->lockHandle, $exclusive ? LOCK_EX : LOCK_SH)) {
                throw new RuntimeException('No se pudo bloquear la base de datos.');
            }

            $this->lockIsExclusive = $exclusive;
        } elseif ($exclusive && !$this->lockIsExclusive) {
            // Subir de compartido a exclusivo: aún no se ha escrito nada,
            // así que se libera y se vuelve a pedir sin riesgo de estado inconsistente.
            flock($this->lockHandle, LOCK_UN);

            if (!flock($this->lockHandle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear la base de datos.');
            }

            $this->lockIsExclusive = true;
        }

        $this->lockDepth++;
    }

    /** Libera el bloqueo adquirido por lock(). */
    public function unlock(): void
    {
        if ($this->lockDepth === 0) {
            return;
        }

        $this->lockDepth--;

        if ($this->lockDepth === 0) {
            flock($this->lockHandle, LOCK_UN);
            $this->lockIsExclusive = false;
        }
    }

    /** ¿Hay una transacción abierta? */
    public function inTransaction(): bool
    {
        return $this->journal !== null;
    }

    /**
     * Ejecuta $fn dentro de una transacción (bloqueo exclusivo mantenido
     * durante toda la operación). Si $fn lanza una excepción se revierten
     * todos los ficheros modificados (rollback) y la excepción se relanza.
     *
     * @param callable(JsonDatabase): mixed $fn
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->journal !== null) {
            throw new RuntimeException('No se admiten transacciones anidadas.');
        }

        $this->lock(true);
        $this->journal = [];

        try {
            $result = $fn($this);
        } catch (Throwable $exception) {
            $this->rollback();
            $this->unlock();
            throw $exception;
        }

        $this->journal = null;
        $this->unlock();

        return $result;
    }

    /** Lee un documento. Devuelve null si no existe. */
    public function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el documento: ' . $path);
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Documento corrupto (JSON no válido): ' . $path, 0, $exception);
        }

        if (!is_array($document)) {
            throw new RuntimeException('Documento no válido (se esperaba un objeto JSON): ' . $path);
        }

        return $document;
    }

    /** Escribe un documento de forma atómica (temporal + rename). */
    public function writeJson(string $path, array $document): void
    {
        try {
            $json = json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('No se pudo serializar el documento: ' . $path, 0, $exception);
        }

        $this->writeRaw($path, $json);
    }

    /** Elimina el fichero de un documento (registrándolo para el rollback). */
    public function deleteFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $this->remember($path);

        if (!@unlink($path)) {
            throw new RuntimeException('No se pudo eliminar el documento: ' . $path);
        }
    }

    private function writeRaw(string $path, string $contents): void
    {
        $this->remember($path);

        $temporary = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));

        if (@file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException('No se pudo escribir el documento: ' . $path);
        }

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo guardar el documento: ' . $path);
        }
    }

    /** Anota el contenido previo del fichero para poder revertirlo (rollback). */
    private function remember(string $path): void
    {
        if ($this->journal === null || array_key_exists($path, $this->journal)) {
            return;
        }

        $this->journal[$path] = is_file($path) ? (string) @file_get_contents($path) : null;
    }

    /** Revierte todos los ficheros tocados en la transacción que ha fallado. */
    private function rollback(): void
    {
        $journal = $this->journal;
        $this->journal = null;

        foreach ($journal ?? [] as $path => $original) {
            if ($original === null) {
                if (is_file($path)) {
                    @unlink($path);
                }

                continue;
            }

            $this->writeRaw($path, $original);
        }
    }
}
