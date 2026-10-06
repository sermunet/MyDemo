/**
 * JavaScript del BackOffice: mejoras de experiencia únicamente.
 * La seguridad (validación, CSRF, sesión) vive siempre en el servidor.
 */
(function () {
  'use strict';

  /* --------------------- Alternancia Campos / JSON --------------------- */

  var form = document.querySelector('[data-bo-form]');

  if (form) {
    var radios = form.querySelectorAll('[data-bo-mode-radio]');
    var paneles = form.querySelectorAll('[data-bo-mode]');

    function aplicarModo() {
      var modo = 'campos';

      radios.forEach(function (radio) {
        if (radio.checked) {
          modo = radio.value;
        }
      });

      paneles.forEach(function (panel) {
        // Sin JavaScript se ven los dos y manda el radio enviado al servidor.
        panel.hidden = panel.getAttribute('data-bo-mode') !== modo;
      });
    }

    radios.forEach(function (radio) {
      radio.addEventListener('change', aplicarModo);
    });

    aplicarModo();

    /* ------------------------- Formatear JSON ------------------------- */

    var botonFormato = form.querySelector('[data-bo-format]');
    var areaJson = form.querySelector('textarea[name="json"]');
    var mensaje = form.querySelector('[data-bo-format-msg]');

    if (botonFormato && areaJson) {
      botonFormato.addEventListener('click', function () {
        try {
          var datos = JSON.parse(areaJson.value);
          areaJson.value = JSON.stringify(datos, null, 2);
          if (mensaje) {
            mensaje.textContent = '✔ JSON correcto';
          }
        } catch (error) {
          if (mensaje) {
            mensaje.textContent = '✕ ' + error.message;
          }
        }
      });
    }

    /* ------------- Aviso al salir con cambios sin guardar ------------- */

    var inicial = serializar(form);

    form.addEventListener('input', function () {
      form.dataset.boTocado = serializar(form) !== inicial ? '1' : '';
    });

    // Al enviar el formulario no se avisa: la navegación es la guardada.
    form.addEventListener('submit', function () {
      form.dataset.boTocado = '';
    });

    window.addEventListener('beforeunload', function (evento) {
      if (form.dataset.boTocado === '1') {
        evento.preventDefault();
        evento.returnValue = '';
      }
    });
  }

  function serializar(formulario) {
    var partes = [];

    formulario.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (campo) {
      if (campo.type === 'radio') {
        if (campo.checked) {
          partes.push(campo.name + '=' + campo.value);
        }
        return;
      }

      if (campo.type === 'checkbox') {
        partes.push(campo.name + '=' + (campo.checked ? '1' : '0'));
        return;
      }

      partes.push(campo.name + '=' + campo.value);
    });

    return partes.join('&');
  }
})();
