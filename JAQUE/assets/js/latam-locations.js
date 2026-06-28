(function () {
  const locations = {
    'Costa Rica': ['San Jose', 'Alajuela', 'Cartago', 'Heredia', 'Guanacaste', 'Puntarenas', 'Limon'],
    'Mexico': ['Ciudad de Mexico', 'Jalisco', 'Nuevo Leon', 'Yucatan', 'Puebla', 'Queretaro'],
    'Guatemala': ['Guatemala', 'Sacatepequez', 'Quetzaltenango', 'Escuintla', 'Alta Verapaz'],
    'El Salvador': ['San Salvador', 'La Libertad', 'Santa Ana', 'San Miguel'],
    'Honduras': ['Francisco Morazan', 'Cortes', 'Atlantida', 'Comayagua'],
    'Panama': ['Panama', 'Panama Oeste', 'Chiriqui', 'Colon'],
    'Colombia': ['Bogota D.C.', 'Antioquia', 'Valle del Cauca', 'Atlantico', 'Cundinamarca'],
    'Ecuador': ['Pichincha', 'Guayas', 'Azuay', 'Manabi'],
    'Peru': ['Lima', 'Arequipa', 'Cusco', 'La Libertad'],
    'Bolivia': ['La Paz', 'Santa Cruz', 'Cochabamba', 'Chuquisaca'],
    'Chile': ['Region Metropolitana', 'Valparaiso', 'Biobio', 'Maule'],
    'Argentina': ['Buenos Aires', 'CABA', 'Cordoba', 'Santa Fe', 'Mendoza'],
    'Uruguay': ['Montevideo', 'Canelones', 'Maldonado', 'Colonia'],
    'Paraguay': ['Asuncion', 'Central', 'Alto Parana', 'Itapua'],
    'Republica Dominicana': ['Distrito Nacional', 'Santo Domingo', 'Santiago', 'La Altagracia']
  };

  function fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach((item) => {
      const option = document.createElement('option');
      option.value = item;
      option.textContent = item;
      select.appendChild(option);
    });
  }

  function setupGroup(group) {
    const country = group.querySelector('[data-country-select]');
    const province = group.querySelector('[data-province-select]');
    if (!country) return;

    fillSelect(country, Object.keys(locations), 'Selecciona pais');

    country.addEventListener('change', () => {
      const provinces = locations[country.value] || [];
      if (province) {
        fillSelect(province, provinces, 'Selecciona provincia / estado');
        province.disabled = provinces.length === 0;
      }
      country.classList.remove('error');
    });

    if (province) {
      province.addEventListener('change', () => {
        province.classList.remove('error');
      });
    }
  }

  window.TuyoMallLocations = locations;

  function initLocationSelects() {
    document.querySelectorAll('[data-location-group]').forEach(setupGroup);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLocationSelects);
  } else {
    initLocationSelects();
  }
})();
