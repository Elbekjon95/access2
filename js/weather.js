import { showModal } from "./ui.js";

export async function initWeather() {
    const weatherBtn = document.getElementById('weather-temp-btn');
    const weatherModal = document.getElementById('weather-modal');
    const weatherGrid = document.getElementById('weather-grid');
    const tempDisplay = document.getElementById('toshkent-temp');

    if (!tempDisplay || !weatherBtn) return;

    try {
        const res = await fetch('api/weather.php?city=Tashkent');
        const data = await res.json();
        if (data && data.success) {
            tempDisplay.textContent = Math.round(data.data.temp) + "°C";
        }
    } catch (err) {
        console.error("Toshkent weather error:", err);
    }

    weatherBtn.onclick = async () => {
        showModal(weatherModal);
        
        weatherGrid.innerHTML = '<div class="loader-container" style="grid-column: 1/-1; text-align: center; padding: 3rem;"><i class="fas fa-circle-notch fa-spin fa-2x"></i><p style="margin-top: 1rem;">Yuklanmoqda...</p></div>';

        try {
            const mapRes = await fetch('api/destination_cities.php');
            const mapData = await mapRes.json();
            if (mapData && mapData.cities && mapData.cities.length > 0) {
                const citiesStr = mapData.cities.join(',');
                const weatherRes = await fetch(`api/weather.php?cities=${citiesStr}`);
                const weatherData = await weatherRes.json();
                
                if (weatherData && weatherData.success) {
                    renderWeatherCards(weatherData.results);
                } else {
                    weatherGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center;">Ob-havo ma\'lumotlarini olib bo\'lmadi.</p>';
                }
            } else {
                weatherGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center;">Hozircha manzil shaharlar topilmadi.</p>';
            }
        } catch (err) {
            console.error("Weather modal error:", err);
            weatherGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center;">Xatolik yuz berdi.</p>';
        }
    };
}

function renderWeatherCards(results) {
    const weatherGrid = document.getElementById('weather-grid');
    weatherGrid.innerHTML = '';
    
    results.forEach(w => {
        const card = document.createElement('div');
        card.className = 'weather-card';
        card.innerHTML = `
            <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem">${w.city}</div>
            <div style="font-size: 2rem; color: var(--secondary-blue);">${w.temp}°C</div>
            <img src="https://openweathermap.org/img/wn/${w.icon}@2x.png" alt="${w.description}">
            <div style="text-transform: capitalize;">${w.description}</div>
            <div style="font-size: 0.85rem; opacity: 0.8; margin-top: 5px;">Namlik: ${w.humidity}%</div>
        `;
        weatherGrid.appendChild(card);
    });
}
