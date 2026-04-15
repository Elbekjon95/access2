import { showModal } from "./ui.js";

let allCities = [];
let loadedCount = 0;
const BATCH_SIZE = 6;

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
                allCities = mapData.cities;
                loadedCount = 0;
                weatherGrid.innerHTML = '';
                await loadMoreWeather();
            } else {
                weatherGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center;">Hozircha manzil shaharlar topilmadi.</p>';
            }
        } catch (err) {
            console.error("Weather modal error:", err);
            weatherGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center;">Xatolik yuz berdi.</p>';
        }
    };
}

async function loadMoreWeather() {
    const weatherGrid = document.getElementById('weather-grid');
    
    // Keyingi 6 ta shaharni olish
    const nextBatch = allCities.slice(loadedCount, loadedCount + BATCH_SIZE);
    if (nextBatch.length === 0) return;
    
    // Loading indicator qo'shish
    const loaderId = 'loader-' + Date.now();
    const loader = document.createElement('div');
    loader.id = loaderId;
    loader.className = 'loader-container';
    loader.style.cssText = 'grid-column: 1/-1; text-align: center; padding: 2rem;';
    loader.innerHTML = '<i class="fas fa-circle-notch fa-spin fa-2x"></i>';
    weatherGrid.appendChild(loader);
    
    try {
        const citiesStr = nextBatch.join(',');
        const weatherRes = await fetch(`api/weather.php?cities=${citiesStr}`);
        const weatherData = await weatherRes.json();
        
        // Loader'ni o'chirish
        const loaderEl = document.getElementById(loaderId);
        if (loaderEl) loaderEl.remove();
        
        if (weatherData && weatherData.success) {
            renderWeatherCards(weatherData.results);
            loadedCount += nextBatch.length;
            
            // Agar yana shaharlar bo'lsa, "Ko'proq yuklash" tugmasini ko'rsatish
            updateLoadMoreButton();
        }
    } catch (err) {
        console.error("Load more weather error:", err);
        const loaderEl = document.getElementById(loaderId);
        if (loaderEl) loaderEl.remove();
    }
}

function updateLoadMoreButton() {
    const weatherGrid = document.getElementById('weather-grid');
    const existingBtn = document.getElementById('load-more-weather-btn');
    
    if (existingBtn) existingBtn.remove();
    
    if (loadedCount < allCities.length) {
        const btnContainer = document.createElement('div');
        btnContainer.id = 'load-more-weather-btn';
        btnContainer.style.cssText = 'grid-column: 1/-1; text-align: center; padding: 1rem;';
        btnContainer.innerHTML = `
            <button class="nav-btn" style="padding: 0.8rem 2rem; font-size: 0.9rem;">
                <i class="fas fa-plus-circle"></i> Ko'proq yuklash (${allCities.length - loadedCount} ta qoldi)
            </button>
        `;
        btnContainer.querySelector('button').onclick = loadMoreWeather;
        weatherGrid.appendChild(btnContainer);
    }
}

function renderWeatherCards(results) {
    const weatherGrid = document.getElementById('weather-grid');
    
    // Eski "Ko'proq yuklash" tugmasini o'chirish
    const existingBtn = document.getElementById('load-more-weather-btn');
    if (existingBtn) existingBtn.remove();
    
    // Yangi kartalarni qo'shish (eski kartalar saqlanadi)
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
