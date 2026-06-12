(function () {
  const locations = {
    "Costa Rica": {
      "San José": ["San José", "Escazú", "Desamparados", "Puriscal", "Tarrazú", "Aserrí", "Mora", "Goicoechea", "Santa Ana", "Alajuelita", "Vázquez de Coronado", "Acosta", "Tibás", "Moravia", "Montes de Oca", "Turrubares", "Dota", "Curridabat", "Pérez Zeledón", "León Cortés"],
      "Alajuela": ["Alajuela", "San Ramón", "Grecia", "San Mateo", "Atenas", "Naranjo", "Palmares", "Poás", "Orotina", "San Carlos", "Zarcero", "Valverde Vega", "Upala", "Los Chiles", "Guatuso", "Río Cuarto"],
      "Cartago": ["Cartago", "Paraíso", "La Unión", "Jiménez", "Turrialba", "Alvarado", "Oreamuno", "El Guarco"],
      "Heredia": ["Heredia", "Barva", "Santo Domingo", "Santa Bárbara", "San Rafael", "San Isidro", "Belén", "Flores", "San Pablo", "Sarapiquí"],
      "Guanacaste": ["Liberia", "Nicoya", "Santa Cruz", "Bagaces", "Carrillo", "Cañas", "Abangares", "Tilarán", "Nandayure", "La Cruz", "Hojancha"],
      "Puntarenas": ["Puntarenas", "Esparza", "Buenos Aires", "Montes de Oro", "Osa", "Quepos", "Golfito", "Coto Brus", "Parrita", "Corredores", "Garabito", "Monteverde", "Puerto Jiménez"],
      "Limón": ["Limón", "Pococí", "Siquirres", "Talamanca", "Matina", "Guácimo"]
    },
    "México": {
      "Ciudad de México": ["Cuauhtémoc", "Miguel Hidalgo", "Benito Juárez", "Coyoacán", "Iztapalapa"],
      "Jalisco": ["Guadalajara", "Zapopan", "Tlaquepaque", "Tonalá", "Puerto Vallarta"],
      "Nuevo León": ["Monterrey", "San Pedro Garza García", "Guadalupe", "San Nicolás", "Apodaca"],
      "Yucatán": ["Mérida", "Valladolid", "Progreso", "Tizimín"]
    },
    "Guatemala": {
      "Guatemala": ["Ciudad de Guatemala", "Mixco", "Villa Nueva", "Santa Catarina Pinula"],
      "Sacatepéquez": ["Antigua Guatemala", "Jocotenango", "Ciudad Vieja"],
      "Quetzaltenango": ["Quetzaltenango", "Salcajá", "Coatepeque"],
      "Escuintla": ["Escuintla", "Santa Lucía Cotzumalguapa", "Puerto San José"]
    },
    "El Salvador": {
      "San Salvador": ["San Salvador", "Soyapango", "Mejicanos", "Apopa"],
      "La Libertad": ["Santa Tecla", "Antiguo Cuscatlán", "Colón"],
      "Santa Ana": ["Santa Ana", "Chalchuapa", "Metapán"],
      "San Miguel": ["San Miguel", "Chinameca", "Moncagua"]
    },
    "Honduras": {
      "Francisco Morazán": ["Tegucigalpa", "Valle de Ángeles", "Santa Lucía"],
      "Cortés": ["San Pedro Sula", "Choloma", "Puerto Cortés"],
      "Atlántida": ["La Ceiba", "Tela", "El Porvenir"],
      "Comayagua": ["Comayagua", "Siguatepeque", "La Libertad"]
    },
    "Nicaragua": {
      "Managua": ["Managua", "Tipitapa", "Ciudad Sandino"],
      "León": ["León", "Nagarote", "La Paz Centro"],
      "Granada": ["Granada", "Nandaime", "Diriomo"],
      "Matagalpa": ["Matagalpa", "Sébaco", "San Ramón"]
    },
    "Panamá": {
      "Panamá": ["Ciudad de Panamá", "San Miguelito", "Tocumen"],
      "Panamá Oeste": ["La Chorrera", "Arraiján", "Capira"],
      "Chiriquí": ["David", "Boquete", "Bugaba"],
      "Colón": ["Colón", "Portobelo", "Chagres"]
    },
    "Colombia": {
      "Bogotá D.C.": ["Bogotá"],
      "Antioquia": ["Medellín", "Envigado", "Bello", "Rionegro"],
      "Valle del Cauca": ["Cali", "Palmira", "Buenaventura"],
      "Atlántico": ["Barranquilla", "Soledad", "Malambo"]
    },
    "Venezuela": {
      "Distrito Capital": ["Caracas"],
      "Miranda": ["Chacao", "Baruta", "Los Teques", "Guatire"],
      "Carabobo": ["Valencia", "Naguanagua", "Puerto Cabello"],
      "Zulia": ["Maracaibo", "San Francisco", "Cabimas"]
    },
    "Ecuador": {
      "Pichincha": ["Quito", "Cayambe", "Rumiñahui"],
      "Guayas": ["Guayaquil", "Daule", "Samborondón"],
      "Azuay": ["Cuenca", "Gualaceo", "Paute"],
      "Manabí": ["Manta", "Portoviejo", "Jipijapa"]
    },
    "Perú": {
      "Lima": ["Lima", "Miraflores", "San Isidro", "Surco"],
      "Arequipa": ["Arequipa", "Cayma", "Yanahuara"],
      "Cusco": ["Cusco", "San Sebastián", "Wanchaq"],
      "La Libertad": ["Trujillo", "Víctor Larco Herrera", "Huanchaco"]
    },
    "Bolivia": {
      "La Paz": ["La Paz", "El Alto", "Viacha"],
      "Santa Cruz": ["Santa Cruz de la Sierra", "Montero", "Warnes"],
      "Cochabamba": ["Cochabamba", "Quillacollo", "Sacaba"],
      "Chuquisaca": ["Sucre", "Yotala", "Tarabuco"]
    },
    "Chile": {
      "Región Metropolitana": ["Santiago", "Providencia", "Las Condes", "Ñuñoa"],
      "Valparaíso": ["Valparaíso", "Viña del Mar", "Quilpué"],
      "Biobío": ["Concepción", "Talcahuano", "Los Ángeles"],
      "Maule": ["Talca", "Curicó", "Linares"]
    },
    "Argentina": {
      "Buenos Aires": ["La Plata", "Mar del Plata", "Bahía Blanca", "Tigre"],
      "CABA": ["Buenos Aires"],
      "Córdoba": ["Córdoba", "Villa Carlos Paz", "Río Cuarto"],
      "Santa Fe": ["Rosario", "Santa Fe", "Rafaela"],
      "Mendoza": ["Mendoza", "Godoy Cruz", "San Rafael"]
    },
    "Uruguay": {
      "Montevideo": ["Montevideo"],
      "Canelones": ["Ciudad de la Costa", "Las Piedras", "Pando"],
      "Maldonado": ["Maldonado", "Punta del Este", "San Carlos"],
      "Colonia": ["Colonia del Sacramento", "Carmelo", "Nueva Helvecia"]
    },
    "Paraguay": {
      "Asunción": ["Asunción"],
      "Central": ["San Lorenzo", "Luque", "Fernando de la Mora", "Capiatá"],
      "Alto Paraná": ["Ciudad del Este", "Presidente Franco", "Hernandarias"],
      "Itapúa": ["Encarnación", "Hohenau", "Cambyretá"]
    },
    "República Dominicana": {
      "Distrito Nacional": ["Santo Domingo"],
      "Santo Domingo": ["Santo Domingo Este", "Santo Domingo Norte", "Santo Domingo Oeste"],
      "Santiago": ["Santiago de los Caballeros", "Tamboril", "Licey al Medio"],
      "La Altagracia": ["Higüey", "Punta Cana", "Bávaro"]
    }
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
    const canton = group.querySelector('[data-canton-select]');
    if (!country || !province) return;

    fillSelect(country, Object.keys(locations), 'Seleccioná país');

    country.addEventListener('change', () => {
      const provinces = Object.keys(locations[country.value] || {});
      fillSelect(province, provinces, 'Seleccioná provincia');
      if (canton) fillSelect(canton, [], 'Primero elegí provincia');
      province.disabled = provinces.length === 0;
      if (canton) canton.disabled = true;
      country.classList.remove('error');
    });

    province.addEventListener('change', () => {
      const cantons = (locations[country.value] || {})[province.value] || [];
      if (canton) {
        fillSelect(canton, cantons, 'Seleccioná cantón / ciudad');
        canton.disabled = cantons.length === 0;
      }
      province.classList.remove('error');
    });

    if (canton) {
      canton.addEventListener('change', () => {
        canton.classList.remove('error');
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
