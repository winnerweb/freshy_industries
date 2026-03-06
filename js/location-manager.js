/**
 * Gestionnaire de localisation pour les dropdowns Pays/Villes
 * Structure de données pour l'Afrique de l'Ouest
 */
window.LocationManager = window.LocationManager || {
    // Base de données pays → villes
    locations: {
        'Sénégal': ['Dakar', 'Thiès', 'Kaolack', 'Saint-Louis', 'Mbour', 'Ziguinchor', 'Diourbel', 'Touba', 'Lougga', 'Fatick', 'Kédougou', 'Matam', 'Kaffrine', 'Kolda', 'Tambacounda', 'Sédhiou', 'Bakel'],
        'Côte d\'Ivoire': ['Abidjan', 'Bouaké', 'Yamoussoukro', 'San-Pedro', 'Daloa', 'Gagnoa', 'Man', 'Sassandra', 'Abengourou', 'Soubré', 'Divo', 'Issia', 'Korhogo', 'Bondoukou', 'Bouaflé', 'Oumé', 'Touléplekin', 'Grand-Bassam', 'Bouna', 'M\'Bahiako', 'Adzopé', 'Akoupé', 'Alépé', 'Jacqueville', 'Sakassou', 'Dabou', 'Tanda', 'Guiglo', 'Béoumi', 'Bettié', 'Biankouma', 'Attiégué', 'Dabakala', 'Séguéla', 'Odienné', 'Ferkessédougou', 'Maféria', 'Bondoukou', 'Touléplekin', 'Daoukro', 'Séguéla', 'Oumé', 'Bouna', 'M\'Bahiako', 'Adzopé', 'Akoupé', 'Alépé', 'Jacqueville', 'Sakassou', 'Dabou', 'Tanda', 'Guiglo', 'Béoumi', 'Bettié', 'Biankouma'],
        'Mali': ['Bamako', 'Sikasso', 'Koulikoro', 'Ségou', 'Mopti', 'Kayes', 'Kita', 'Koro', 'Sikasso', 'Mopti', 'Kayes', 'Kita', 'Koro', 'Sikasso', 'Mopti', 'Kayes', 'Kita', 'Koro'],
        'Burkina Faso': ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Kadiogo', 'Ouahigouya', 'Banfora', 'Tenkodogo', 'Fada N\'Gourma', 'Kaya', 'Koudougou', 'Kadiogo', 'Ouahigouya', 'Banfora', 'Tenkodogo', 'Fada N\'Gourma', 'Kaya'],
        'Bénin': ['Porto-Novo', 'Cotonou', 'Parakou', 'Abomey-Calavi', 'Lokossa', 'Ouidah', 'Abomey-Calavi', 'Lokossa', 'Ouidah'],
        'Togo': ['Lomé', 'Sokodé', 'Kara', 'Kpalimé', 'Atakpamé', 'Tsévié', 'Anié', 'Pagouda', 'Bafilo', 'Bassar', 'Dapaong', 'Mango', 'Notsé'],
        'Niger': ['Niamey', 'Zinder', 'Maradi', 'Agadez', 'Tahoua', 'Dosso', 'Birni N\'Konni', 'Tillabéri', 'Kollo', 'Gaya', 'Diffa', 'Dogondoutchi', 'Téra', 'Mirriah', 'Madaoua', 'Arlit', 'Tessaoua', 'Maine-Soroa', 'Kollo', 'Gaya', 'Diffa', 'Dogondoutchi', 'Téra', 'Mirriah', 'Madaoua', 'Arlit', 'Tessaoua', 'Maine-Soroa'],
        'Guinée': ['Conakry', 'Nzérékoré', 'Kankan', 'Kindia', 'Faranah', 'Boké', 'Kérouané', 'Mamou', 'Dabola', 'Boké', 'Kérouané', 'Mamou', 'Dabola'],
        'Ghana': ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Sunyani', 'Obuasi', 'Techiman', 'Wa', 'Koforidua', 'Sefwi Wiawso', 'Bawku', 'Berekum', 'Dunkwa-on-Offin', 'Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Sunyani', 'Obuasi', 'Techiman', 'Wa', 'Koforidua', 'Sefwi Wiawso', 'Bawku', 'Berekum', 'Dunkwa-on-Offin'],
        'Nigeria': ['Lagos', 'Abuja', 'Kano', 'Ibadan', 'Kaduna', 'Port Harcourt', 'Benin City', 'Maiduguri', 'Zaria', 'Aba', 'Jos', 'Ilorin', 'Oyo', 'Ondo', 'Ekiti', 'Akwaba', 'Uyo', 'Calabar', 'Warri', 'Benin City', 'Maiduguri', 'Zaria', 'Aba', 'Jos', 'Ilorin', 'Oyo', 'Ondo', 'Ekiti', 'Akwaba', 'Uyo', 'Calabar', 'Warri'],
        'Gambie': ['Banjul', 'Serekunda', 'Brikama', 'Bakau', 'Farafenni', 'Kotu', 'Mansakonko', 'Kerewan', 'Basang', 'Soma', 'Basse Santa', 'Sukuta', 'Basse Santa', 'Sukuta'],
        'Sierra Leone': ['Freetown', 'Bo', 'Kenema', 'Makeni', 'Koidu', 'Port Loko', 'Pujehun', 'Kabala', 'Kabala', 'Kabala'],
        'Libéria': ['Monrovia', 'Gbarnga', 'Buchanan', 'Ganta', 'Kakata', 'Harper', 'Voinjama', 'Bensonville', 'Greenville', 'Robertsport', 'Sanniquellie', 'Harper', 'Bensonville', 'Greenville', 'Robertsport', 'Sanniquellie'],
        'Cap-Vert': ['Praia', 'Mindelo', 'Cidade da Praia', 'Assomada', 'Santa Maria', 'São Vicente', 'Tarrafal', 'Santa Catarina do Fogo', 'Brava', 'Santa Maria', 'São Vicente', 'Tarrafal', 'Santa Catarina do Fogo', 'Brava'],
        'Tchad': ['N\'Djaména', 'Moundou', 'Sarh', 'Abéché', 'Doba', 'Kélo', 'Mongo', 'Bongor', 'Ati', 'Oum Hadjer', 'Moundou', 'Sarh', 'Abéché', 'Doba', 'Kélo', 'Mongo', 'Bongor', 'Ati', 'Oum Hadjer']
    },

    /**
     * Initialise les gestionnaires d'événements
     */
    init() {
        const countrySelect = document.getElementById('country');
        const citySelect = document.getElementById('city');

        if (!countrySelect || !citySelect) return;

        // Peupler les pays au chargement
        this.populateCountries(countrySelect);

        // Écouteur pour le changement de pays
        countrySelect.addEventListener('change', (e) => {
            this.handleCountryChange(e.target.value, citySelect);
        });

        // Écouteur pour le changement de ville
        citySelect.addEventListener('change', (e) => {
            this.handleCityChange(e.target.value);
        });
    },

    /**
     * Gère le changement de pays
     */
    handleCountryChange(selectedCountry, citySelect) {
        // Vider le dropdown des villes
        citySelect.innerHTML = '<option value="" disabled selected>Ville</option>';
        
        if (!selectedCountry) {
            return;
        }

        // Ajouter les villes correspondantes
        const cities = this.locations[selectedCountry] || [];
        cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
        });
    },

    /**
     * Gère le changement de ville
     */
    handleCityChange(selectedCity) {
        // Validation simple
        if (!selectedCity) {
            console.warn('Aucune ville sélectionnée');
            return;
        }
        
        console.log(`Ville sélectionnée: ${selectedCity}`);
    },

    /**
     * Réinitialise les formulaires
     */
    reset() {
        const countrySelect = document.getElementById('country');
        const citySelect = document.getElementById('city');
        
        if (countrySelect) {
            countrySelect.value = '';
            countrySelect.innerHTML = '<option value="" disabled selected>Pays</option>';
            this.populateCountries(countrySelect);
        }
        
        if (citySelect) {
            citySelect.value = '';
            citySelect.innerHTML = '<option value="" disabled selected>Ville</option>';
        }
    },

    /**
     * Peuple le dropdown des pays
     */
    populateCountries(countrySelect) {
        const countries = Object.keys(this.locations);
        
        countries.forEach(country => {
            const option = document.createElement('option');
            option.value = country;
            option.textContent = country;
            countrySelect.appendChild(option);
        });
    }
};

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    window.LocationManager.init();
});

