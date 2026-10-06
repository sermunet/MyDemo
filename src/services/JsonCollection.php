<?php

/**
 * Colección de la base de datos NoSQL: carpeta data/<coleccion>/ con un
 * fichero JSON por documento (AGENTS.md §5 y §6).
 *
 * Filtros (find, findOne, count, deleteWhere):
 *   - Igualdad:          ['estado' => 'publicado']
 *   - Campo anidado:     ['autor.nombre' => 'Ana']
 *   - Operadores:        ['visitas' => ['$gte' => 10, '$lt' => 100]]
 *     $eq $ne $gt $gte $lt $lte $in $nin $exists $contains $startsWith
 *
 * Opciones de find(): ['sort' => ['campo' => 1|-1], 'limit' => n, 'skip' => n]
 *
 * IMPORTANTE (AGENTS.md §3): las claves de filtro, orden y campos salen del
 * código, nunca directamente de $_GET/$_POST sin pasar por una whitelist.
 */
final class JsonCollection
{
    private JsonDatabase $db;
    private string $name;

    public function __construct(JsonDatabase $db, string $name)
    {
        if (!preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $name)) {
            throw new InvalidArgumentException(
                'Nombre de colección no válido: "' . $name
                . '". Usa minúsculas, dígitos y guion bajo (máx. 64).'
            );
        }

        $this->db = $db;
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** Carpeta de la colección dentro de data/. */
    public function dir(): string
    {
        return $this->db->root() . '/' . $this->name;
    }

    /** Crea la carpeta de la colección si aún no existe. */
    public function ensure(): void
    {
        $this->db->ensureDir($this->dir());
    }

    /**
     * Inserta un documento nuevo. Si no trae "id" se genera uno; no se
     * permite sobrescribir un documento existente (usa update/upsert).
     */
    public function insert(array $document): array
    {
        if (isset($document['id'])) {
            if (!is_string($document['id'])) {
                throw new InvalidArgumentException('El campo "id" debe ser una cadena de texto.');
            }
        } else {
            $document['id'] = $this->newId();
        }

        $this->assertId($document['id']);

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $document['created_at'] = $document['created_at'] ?? $now;
        $document['updated_at'] = $now;

        $path = $this->path($document['id']);

        $this->db->lock(true);

        try {
            if (is_file($path)) {
                throw new RuntimeException(
                    sprintf('El documento ya existe: %s/%s (usa update() o upsert()).', $this->name, $document['id'])
                );
            }

            $this->ensure();
            $this->db->writeJson($path, $document);
        } finally {
            $this->db->unlock();
        }

        return $document;
    }

    /**
     * Inserta varios documentos en una sola transacción (todo o nada).
     *
     * @return array<int, array>
     */
    public function insertMany(array $documents): array
    {
        $run = function () use ($documents): array {
            $inserted = [];

            foreach ($documents as $document) {
                if (!is_array($document)) {
                    throw new InvalidArgumentException('Cada documento debe ser un array.');
                }

                $inserted[] = $this->insert($document);
            }

            return $inserted;
        };

        return $this->db->inTransaction() ? $run() : $this->db->transaction($run);
    }

    /** Documento por id, o null si no existe. */
    public function findById(string $id): ?array
    {
        $this->assertId($id);

        $this->db->lock(false);

        try {
            return $this->db->readJson($this->path($id));
        } finally {
            $this->db->unlock();
        }
    }

    /**
     * Busca documentos.
     *
     * @param array $filter  Condiciones (ver docblock de la clase).
     * @param array $options ['sort' => [...], 'limit' => int, 'skip' => int]
     * @return array<int, array>
     */
    public function find(array $filter = [], array $options = []): array
    {
        $sort   = $options['sort'] ?? [];
        $limit  = isset($options['limit']) ? max(0, (int) $options['limit']) : null;
        $offset = isset($options['skip']) ? max(0, (int) $options['skip']) : 0;

        if (!is_array($sort)) {
            throw new InvalidArgumentException('La opción "sort" debe ser un array: campo => 1 (asc) o -1 (desc).');
        }

        $documents = $this->select($filter);

        if ($sort !== []) {
            usort($documents, static fn (array $a, array $b): int => self::compareForSort($a, $b, $sort));
        }

        if ($offset > 0) {
            $documents = array_slice($documents, $offset);
        }

        if ($limit !== null) {
            $documents = array_slice($documents, 0, $limit);
        }

        return $documents;
    }

    /** Primer documento que cumple el filtro, o null. */
    public function findOne(array $filter = []): ?array
    {
        $documents = $this->find($filter, ['limit' => 1]);

        return $documents[0] ?? null;
    }

    /** Nº de documentos que cumplen el filtro. */
    public function count(array $filter = []): int
    {
        if ($filter === []) {
            $this->db->lock(false);

            try {
                return count($this->files());
            } finally {
                $this->db->unlock();
            }
        }

        return count($this->select($filter));
    }

    /** ¿Existe algún documento que cumpla el filtro? */
    public function exists(array $filter = []): bool
    {
        return $this->count($filter) > 0;
    }

    /**
     * Actualiza campos de un documento existente (fusión superficial; un valor
     * null se guarda como null). Acepta notación de puntos para campos anidados.
     * Devuelve null si el documento no existe. El id no se modifica.
     */
    public function update(string $id, array $changes): ?array
    {
        $path = $this->path($id);

        $this->db->lock(true);

        try {
            $document = $this->db->readJson($path);

            if ($document === null) {
                return null;
            }

            foreach ($changes as $field => $value) {
                if (!is_string($field) || $field === '') {
                    throw new InvalidArgumentException('update(): la clave debe ser un nombre de campo.');
                }

                if ($field === 'id') {
                    continue;
                }

                self::setField($document, $field, $value);
            }

            $document['id'] = $id;
            $document['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');

            $this->db->writeJson($path, $document);

            return $document;
        } finally {
            $this->db->unlock();
        }
    }

    /**
     * Crea el documento si no existe; si existe, lo reemplaza conservando
     * created_at. Requiere "id" explícito (lo usan las semillas).
     */
    public function upsert(array $document): array
    {
        if (!isset($document['id']) || !is_string($document['id'])) {
            throw new InvalidArgumentException('upsert() necesita el campo "id" (cadena).');
        }

        $id   = $document['id'];
        $path = $this->path($id);

        $this->db->lock(true);

        try {
            $current   = $this->db->readJson($path);
            $document['created_at'] = $current['created_at'] ?? $document['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z');
            $document['id'] = $id;
            $document['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');

            $this->ensure();
            $this->db->writeJson($path, $document);

            return $document;
        } finally {
            $this->db->unlock();
        }
    }

    /** Elimina un documento. Devuelve true si existía. */
    public function delete(string $id): bool
    {
        $path = $this->path($id);

        $this->db->lock(true);

        try {
            if (!is_file($path)) {
                return false;
            }

            $this->db->deleteFile($path);

            return true;
        } finally {
            $this->db->unlock();
        }
    }

    /** Elimina todos los documentos que cumplen el filtro. Devuelve cuántos. */
    public function deleteWhere(array $filter): int
    {
        $run = function () use ($filter): int {
            $deleted = 0;

            foreach ($this->select($filter, true) as $document) {
                if ($this->delete((string) $document['id'])) {
                    $deleted++;
                }
            }

            return $deleted;
        };

        return $this->db->inTransaction() ? $run() : $this->db->transaction($run);
    }

    /** Elimina todos los documentos de la colección. Devuelve cuántos. */
    public function truncate(): int
    {
        return $this->deleteWhere([]);
    }

    /**
     * Devuelve los documentos que cumplen el filtro (sin ordenar ni paginar).
     *
     * @param bool $exclusive Usar bloqueo exclusivo (para borrar después).
     * @return array<int, array>
     */
    private function select(array $filter, bool $exclusive = false): array
    {
        $this->db->lock($exclusive);

        try {
            $documents = [];

            foreach ($this->files() as $file) {
                $document = $this->db->readJson($file);

                if ($document !== null && self::matches($document, $filter)) {
                    $documents[] = $document;
                }
            }

            return $documents;
        } finally {
            $this->db->unlock();
        }
    }

    /** Rutas de todos los ficheros (.json) de la colección. */
    private function files(): array
    {
        return glob($this->dir() . '/*.json') ?: [];
    }

    private function path(string $id): string
    {
        $this->assertId($id);

        return $this->dir() . '/' . $id . '.json';
    }

    private function newId(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * El id forma parte de la ruta del fichero: solo caracteres seguros.
     * Esto también evita cualquier intento de path traversal (../).
     */
    private function assertId(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $id)) {
            throw new InvalidArgumentException(
                'Id no válido: "' . $id . '". Usa letras, dígitos, punto, guion y guion bajo (máx. 64).'
            );
        }
    }

    /** Aplica un filtro a un documento. */
    private static function matches(array $document, array $filter): bool
    {
        foreach ($filter as $field => $expected) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('Filtro no válido: la clave debe ser un nombre de campo.');
            }

            if ($field[0] === '$') {
                throw new InvalidArgumentException(
                    'Los operadores de nivel superior no están soportados ("' . $field
                    . '"). Filtra por campo: ["visitas" => ["$gte" => 10]].'
                );
            }

            if (!self::matchValue(self::valueAt($document, $field), $expected)) {
                return false;
            }
        }

        return true;
    }

    /** Compara el valor real con lo esperado (igualdad u operadores). */
    private static function matchValue(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected) && !array_is_list($expected) && self::hasOperators($expected)) {
            foreach ($expected as $operator => $operand) {
                switch ($operator) {
                    case '$eq':
                        if (!self::equals($actual, $operand)) {
                            return false;
                        }
                        break;

                    case '$ne':
                        if (self::equals($actual, $operand)) {
                            return false;
                        }
                        break;

                    case '$gt':
                        if ($actual === null || self::compareValues($actual, $operand) <= 0) {
                            return false;
                        }
                        break;

                    case '$gte':
                        if ($actual === null || self::compareValues($actual, $operand) < 0) {
                            return false;
                        }
                        break;

                    case '$lt':
                        if ($actual === null || self::compareValues($actual, $operand) >= 0) {
                            return false;
                        }
                        break;

                    case '$lte':
                        if ($actual === null || self::compareValues($actual, $operand) > 0) {
                            return false;
                        }
                        break;

                    case '$in':
                        if (!is_array($operand) || !array_is_list($operand)) {
                            throw new InvalidArgumentException('$in necesita una lista de valores.');
                        }

                        $found = false;

                        foreach ($operand as $candidate) {
                            if (self::equals($actual, $candidate)) {
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            return false;
                        }
                        break;

                    case '$nin':
                        if (!is_array($operand) || !array_is_list($operand)) {
                            throw new InvalidArgumentException('$nin necesita una lista de valores.');
                        }

                        foreach ($operand as $candidate) {
                            if (self::equals($actual, $candidate)) {
                                return false;
                            }
                        }
                        break;

                    case '$exists':
                        if ((bool) $operand !== ($actual !== null)) {
                            return false;
                        }
                        break;

                    case '$contains':
                        if (!is_string($operand) || !is_string($actual) || stripos($actual, $operand) === false) {
                            return false;
                        }
                        break;

                    case '$startsWith':
                        if (!is_string($operand) || !is_string($actual) || stripos($actual, $operand) !== 0) {
                            return false;
                        }
                        break;

                    default:
                        throw new InvalidArgumentException('Operador de filtro no soportado: ' . $operator);
                }
            }

            return true;
        }

        return self::equals($actual, $expected);
    }

    private static function hasOperators(array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (is_string($key) && str_starts_with($key, '$')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Igualdad teniendo en cuenta que un campo puede ser una lista
     * (['a', 'b'] coincide con el valor 'b') y que "7" y 7 son equivalentes.
     */
    private static function equals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual) && is_array($expected) && array_is_list($actual) && array_is_list($expected)) {
            return $actual == $expected;
        }

        if (is_array($actual) && array_is_list($actual)) {
            foreach ($actual as $item) {
                if (self::equals($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        if (is_array($expected) && array_is_list($expected)) {
            foreach ($expected as $item) {
                if (self::equals($actual, $item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_array($actual) || is_array($expected)) {
            return $actual == $expected;
        }

        if ($actual === null || $expected === null) {
            return $actual === $expected;
        }

        if (is_bool($actual) || is_bool($expected)) {
            return $actual === $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $actual === $expected;
    }

    /** Ordena/compara valores: null el menor, números antes que textos. */
    private static function compareValues(mixed $a, mixed $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }

        if ($a === null) {
            return -1;
        }

        if ($b === null) {
            return 1;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        if (is_string($a) && is_string($b)) {
            return strcmp($a, $b);
        }

        if (is_bool($a) && is_bool($b)) {
            return (int) $a <=> (int) $b;
        }

        return strcmp((string) json_encode($a), (string) json_encode($b));
    }

    private static function compareForSort(array $a, array $b, array $sort): int
    {
        foreach ($sort as $field => $direction) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('Orden no válido: la clave debe ser un nombre de campo.');
            }

            if (!in_array($direction, [1, -1, '1', '-1'], true)) {
                throw new InvalidArgumentException(
                    'Dirección de orden no válida para "' . $field . '": usa 1 (asc) o -1 (desc).'
                );
            }

            $result = self::compareValues(self::valueAt($a, $field), self::valueAt($b, $field));

            if ($result !== 0) {
                return ((int) $direction) < 0 ? -$result : $result;
            }
        }

        return 0;
    }

    /** Lee un valor del documento; admite notación de puntos ('autor.nombre'). */
    private static function valueAt(array $document, string $field): mixed
    {
        $value = $document;

        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** Escribe un valor dentro del documento; admite notación de puntos. */
    private static function setField(array &$document, string $field, mixed $value): void
    {
        $segments = explode('.', $field);
        $leaf     = array_pop($segments);
        $target   = &$document;

        foreach ($segments as $segment) {
            self::assertFieldSegment($segment);

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        self::assertFieldSegment($leaf);
        $target[$leaf] = $value;
    }

    private static function assertFieldSegment(string $segment): void
    {
        if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9_-]{0,63}$/', $segment)) {
            throw new InvalidArgumentException('Nombre de campo no válido: "' . $segment . '".');
        }
    }
}
