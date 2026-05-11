document.addEventListener('DOMContentLoaded', () => {
    const config = window.EzanVaktimConfig || {};
    const root = document.querySelector('.page-shell');
    const compassPanel = document.querySelector('.compass-panel');
    const compassNeedle = document.querySelector('.compass-needle');

    const elements = {
        headerLocation: document.getElementById('headerLocation'),
        heroLocation: document.getElementById('heroLocation'),
        liveClock: document.getElementById('liveClock'),
        hijriDate: document.getElementById('hijriDate'),
        gregorianDate: document.getElementById('gregorianDate'),
        countdownTime: document.getElementById('countdownTime'),
        currentPrayerEyebrow: document.getElementById('currentPrayerEyebrow'),
        currentPrayerName: document.getElementById('currentPrayerName'),
        currentPrayerWindow: document.getElementById('currentPrayerWindow'),
        statusMessage: document.getElementById('statusMessage'),
        statusPanel: document.getElementById('statusPanel'),
        manualPanel: document.getElementById('manualPanel'),
        searchForm: document.getElementById('locationSearchForm'),
        searchInput: document.getElementById('locationSearchInput'),
        searchResults: document.getElementById('searchResults'),
        geoLocateButton: document.getElementById('geoLocateButton'),
        changeLocationButton: document.getElementById('changeLocationButton'),
        countdownRingProgress: document.getElementById('countdownRingProgress'),
        sunJourneyValue: document.getElementById('sunJourneyValue'),
        sunriseBar: document.getElementById('sunriseBar'),
        sunsetBar: document.getElementById('sunsetBar'),
        qiblaAngle: document.getElementById('qiblaAngle'),
        qiblaMapCanvas: document.getElementById('qiblaMapCanvas'),
        qiblaMapPlaceholder: document.getElementById('qiblaMapPlaceholder'),
        qiblaMapStatus: document.getElementById('qiblaMapStatus'),
        qiblaMapFooter: document.getElementById('qiblaMapFooter'),
        scheduleToggleButton: document.getElementById('scheduleToggleButton'),
        schedulePanel: document.getElementById('schedulePanel'),
        scheduleStatus: document.getElementById('scheduleStatus'),
        monthlyScheduleTableBody: document.getElementById('monthlyScheduleTableBody'),
    };

    const endpoints = {
        search: root?.dataset.searchEndpoint || '',
        prayertimes: root?.dataset.prayertimesEndpoint || '',
        reverse: root?.dataset.reverseEndpoint || '',
        ipGeolocation: config.api?.ipGeolocation || '',
        locationResolve: config.api?.locationResolve || '',
        debugLog: config.api?.debugLog || '',
    };

    const prayerCards = Array.from(document.querySelectorAll('.prayer-card'));
    const prayerMap = {
        imsak: 'fajr',
        gunes: 'sun',
        ogle: 'dhuhr',
        ikindi: 'asr',
        aksam: 'maghrib',
        yatsi: 'isha',
    };
    const prayerOrder = ['fajr', 'sun', 'dhuhr', 'asr', 'maghrib', 'isha'];
    const prayerNames = Object.assign({
        imsak: 'İmsak',
        gunes: 'Güneş',
        ogle: 'Öğle',
        ikindi: 'İkindi',
        aksam: 'Akşam',
        yatsi: 'Yatsı',
    }, config.prayers || {});
    const mecca = { lat: 21.422487, lon: 39.826206 };
    const storageKey = 'ezanvaktim:selected-location';
    const storageVersion = 2;

    const state = {
        selectedLocation: null,
        todayPrayerTimes: null,
        monthPrayerTimes: [],
        countdownTimer: null,
        qiblaMap: null,
        qiblaLine: null,
        qiblaUserMarker: null,
        qiblaMeccaMarker: null,
        qiblaAngle: null,
        deviceOrientationActive: false,
    };

    const isDebugMode = config.debug !== false;

    const logError = (message, error = null) => {
        if (isDebugMode) {
            console.error(message, error);
        }
    };

    const debugLog = async (message, details = {}) => {
        if (isDebugMode) {
            console.log('[EzanVaktim debug]', message, details);
        }

        if (!endpoints.debugLog || !isDebugMode) {
            return;
        }

        try {
            const fetchOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ message, details }),
            };

            if (config.csrfToken) {
                fetchOptions.headers['X-CSRF-Token'] = config.csrfToken;
            }

            await fetch(endpoints.debugLog, fetchOptions);
        } catch (error) {
            if (isDebugMode) {
                console.error('Debug log gonderilemedi:', error);
            }
        }
    };

    const setStatus = (message, tone = 'neutral') => {
        if (!elements.statusMessage || !elements.statusPanel) {
            return;
        }

        const shouldShow = tone === 'error' && Boolean(message);
        elements.statusPanel.hidden = !shouldShow;

        if (!shouldShow) {
            elements.statusMessage.textContent = '';
            elements.statusPanel.dataset.tone = 'neutral';
            return;
        }

        elements.statusMessage.textContent = message;
        elements.statusPanel.dataset.tone = tone;
    };

    const setManualVisible = (visible) => {
        if (elements.manualPanel) {
            elements.manualPanel.hidden = !visible;
        }
    };

    const setLoadingState = (button, loading, label) => {
        if (!button) {
            return;
        }

        button.disabled = loading;
        button.classList.toggle('is-loading', loading);

        const textNode = button.querySelector('span');
        if (textNode && label) {
            textNode.textContent = label;
        }
    };

    const formatQiblaLabel = (trueBearing) => {
        const normalizedBearing = ((trueBearing % 360) + 360) % 360;
        return `${normalizedBearing.toFixed(1)}° Kuzeyden`;
    };

    const updateCompass = (trueBearing) => {
        const normalizedBearing = ((trueBearing % 360) + 360) % 360;
        state.qiblaAngle = normalizedBearing;
        const visualRotation = normalizedBearing - 180;

        if (compassPanel) {
            compassPanel.dataset.angle = String(normalizedBearing);
        }

        if (compassNeedle) {
            compassNeedle.style.setProperty('--angle', `${visualRotation}deg`);
        }

        if (elements.qiblaAngle) {
            elements.qiblaAngle.textContent = formatQiblaLabel(normalizedBearing);
        }
    };

    const initDeviceCompass = () => {
        const start = () => {
            window.addEventListener('deviceorientation', (e) => {
                if (state.qiblaAngle === null || !compassNeedle) return;
                const heading = e.webkitCompassHeading ?? (e.alpha != null ? (360 - e.alpha) : null);
                if (heading === null) return;
                const relativeAngle = state.qiblaAngle - heading;
                compassNeedle.style.setProperty('--angle', `${relativeAngle - 180}deg`);
            }, { passive: true });
            state.deviceOrientationActive = true;
        };

        if (typeof DeviceOrientationEvent?.requestPermission === 'function') {
            const btn = document.createElement('button');
            btn.className = 'compass-permission-btn';
            btn.textContent = 'Pusulayı Etkinleştir';
            btn.addEventListener('click', () => {
                DeviceOrientationEvent.requestPermission().then(permissionState => {
                    if (permissionState === 'granted') { start(); btn.remove(); }
                }).catch(err => logError('DeviceOrientation permission denied:', err));
            });
            compassPanel?.appendChild(btn);
        } else if (window.DeviceOrientationEvent) {
            start();
        }
    };

    const normalizeText = (value) => (value || '')
        .toLocaleLowerCase('tr-TR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ı/g, 'i')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const unique = (values) => [...new Set(values.filter(Boolean))];

    const formatDisplayLocation = (location) => [location.displayRegion || location.region, location.displayCity || location.city].filter(Boolean).join(', ');
    const formatResolvedPrayerLocation = (location) => [location.region || location.displayRegion, location.city || location.displayCity].filter(Boolean).join(', ');
    const getLocationLookupQueries = (location) => unique([
        [
            location.displayRegion || location.region,
            location.displayCity || location.city,
            location.country,
        ].filter(Boolean).join(' ').trim(),
        [
            location.displayCity || location.city,
            location.country,
        ].filter(Boolean).join(' ').trim(),
        location.displayCity || location.city,
        [
            location.region,
            location.city,
            location.country,
        ].filter(Boolean).join(' ').trim(),
        [
            location.city,
            location.country,
        ].filter(Boolean).join(' ').trim(),
        location.city,
    ]);

    const toCoordinates = (item) => {
        const latitude = Number(item?.lat);
        const longitude = Number(item?.lon);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return null;
        }

        return { latitude, longitude };
    };

    const formatDateKey = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const parseApiDate = (dateText) => {
        if (typeof dateText !== 'string' || !dateText.trim()) {
            return new Date(NaN);
        }

        const trimmed = dateText.trim();
        const dateOnlyMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (dateOnlyMatch) {
            const [, year, month, day] = dateOnlyMatch;
            return new Date(Number(year), Number(month) - 1, Number(day));
        }

        return new Date(trimmed);
    };

    const parseTime = (timeText, date) => {
        const [hour, minute] = timeText.split(':').map(Number);
        const clone = new Date(date);
        clone.setHours(hour, minute, 0, 0);
        return clone;
    };

    const formatTime = (date) => new Intl.DateTimeFormat('tr-TR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

    const formatDate = (date) => new Intl.DateTimeFormat('tr-TR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);

    const formatHijriDate = (date) => new Intl.DateTimeFormat('tr-TR-u-ca-islamic', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);

    const updateClock = () => {
        const current = new Date();

        if (elements.liveClock) {
            elements.liveClock.textContent = formatTime(current);
        }

        if (elements.gregorianDate) {
            elements.gregorianDate.textContent = formatDate(current);
        }

        if (elements.hijriDate) {
            elements.hijriDate.textContent = formatHijriDate(current);
        }
    };

    const saveLocation = (location) => {
        localStorage.setItem(storageKey, JSON.stringify({
            ...location,
            version: storageVersion,
        }));
    };

    const loadStoredLocation = () => {
        try {
            const raw = localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    };

    const requestJson = async (url, method = 'GET') => {
        await debugLog('requestJson.start', { url, method });

        const separator = url.includes('?') ? '&' : '?';
        const urlWithToken = url + separator + 'csrf_token=' + encodeURIComponent(config.csrfToken || '');

        const fetchOptions = {
            method,
            headers: {
                Accept: 'application/json',
            },
        };

        if (method === 'POST' && config.csrfToken) {
            fetchOptions.headers['X-CSRF-Token'] = config.csrfToken;
        }

        const response = await fetch(urlWithToken, fetchOptions);

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        await debugLog('requestJson.response', {
            url,
            ok: response.ok,
            status: response.status,
            hasPayload: payload !== null,
        });

        if (!response.ok) {
            const apiMessage = typeof payload?.error === 'string' ? payload.error : '';
            await debugLog('requestJson.error', {
                url,
                status: response.status,
                error: apiMessage || `HTTP ${response.status}`,
            });
            throw new Error(apiMessage || `HTTP ${response.status}`);
        }

        return payload;
    };

    const getErrorMessage = (error, fallbackMessage) => {
        const message = typeof error?.message === 'string' ? error.message.trim() : '';
        return message ? `${fallbackMessage} (${message})` : fallbackMessage;
    };

    const searchLocations = async (query) => {
        const payload = await requestJson(`${endpoints.search}${encodeURIComponent(query)}`);
        return Array.isArray(payload.value) ? payload.value : [];
    };

    const resolveLocationQuery = async (query) => {
        if (!endpoints.locationResolve) {
            return [];
        }

        const payload = await requestJson(`${endpoints.locationResolve}${encodeURIComponent(query)}`);
        return Array.isArray(payload.value) ? payload.value : [];
    };

    const getPrayerTimes = async (locationId) => {
        const payload = await requestJson(`${endpoints.prayertimes}${encodeURIComponent(String(locationId))}`);
        return Array.isArray(payload.value) ? payload.value : [];
    };

    const getReverseGeocode = async (latitude, longitude) => {
        const separator = endpoints.reverse.includes('?') ? '&' : '?';
        const url = `${endpoints.reverse}${separator}lat=${encodeURIComponent(String(latitude))}&lon=${encodeURIComponent(String(longitude))}`;
        return requestJson(url);
    };

    const getIpGeolocation = async () => {
        if (!endpoints.ipGeolocation) {
            throw new Error('ip-geolocation-endpoint-missing');
        }

        return requestJson(endpoints.ipGeolocation);
    };

    const clearSearchResults = () => {
        if (!elements.searchResults) {
            return;
        }

        elements.searchResults.hidden = true;
        elements.searchResults.innerHTML = '';
    };

    const renderSearchResults = (results) => {
        if (!elements.searchResults) {
            return;
        }

        clearSearchResults();

        if (!results.length) {
            setStatus(config.status?.not_found || 'Uygun konum bulunamadı.', 'warning');
            return;
        }

        const fragment = document.createDocumentFragment();

        results.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-result-item';
            const title = item.displayCity || item.city;
            const subtitleParts = [item.displayRegion || item.region];

            if (item.country) {
                subtitleParts.push(item.country);
            }

            button.innerHTML = `
                <strong>${title}</strong>
                <span>${subtitleParts.filter(Boolean).join(', ')}</span>
            `;

            button.addEventListener('click', async () => {
                clearSearchResults();
                if (elements.searchInput) {
                    elements.searchInput.value = formatDisplayLocation(item);
                }
                await selectLocation(item, 'manual');
            });

            fragment.appendChild(button);
        });

        elements.searchResults.appendChild(fragment);
        elements.searchResults.hidden = false;
    };

    const chooseBestLocation = (results, address) => {
        if (!results.length) {
            return null;
        }

        const provinceNorm = normalizeText(address.province);
        const townNorm = normalizeText(address.town);

        const exact = results.find((item) => {
            const itemCity = normalizeText(item.city);
            const itemRegion = normalizeText(item.region);
            const cityMatches = !provinceNorm || itemCity === provinceNorm;
            const districtMatches = !townNorm || itemRegion === townNorm;
            return cityMatches && districtMatches;
        });

        const centerCityMatch = results.find((item) => {
            const itemCity = normalizeText(item.city);
            const itemRegion = normalizeText(item.region);
            return itemCity === provinceNorm && itemRegion === provinceNorm;
        });

        const cityMatch = results.find((item) => normalizeText(item.city) === provinceNorm);

        return exact || centerCityMatch || cityMatch || results[0];
    };

    const chooseBestManualLocation = (results, query) => {
        if (!results.length) {
            return null;
        }

        const queryNorm = normalizeText(query);

        return results.find((item) => normalizeText(item.region) === queryNorm && normalizeText(item.city) === queryNorm)
            || results.find((item) => normalizeText(item.city) === queryNorm)
            || results.find((item) => normalizeText(item.region) === queryNorm)
            || results[0];
    };

    const chooseBestResolvedCoordinateMatch = (resolvedItems, location) => {
        if (!resolvedItems.length) {
            return null;
        }

        const cityNorm = normalizeText(location.displayCity || location.city);
        const regionNorm = normalizeText(location.displayRegion || location.region);
        const countryNorm = normalizeText(location.country);

        return resolvedItems.find((item) => {
            const address = item.address || {};
            const resolvedCity = normalizeText(address.city || address.province || address.state);
            const resolvedRegion = normalizeText(address.town || address.city_district || address.county || address.suburb);
            const resolvedCountry = normalizeText(address.country);
            return (!cityNorm || resolvedCity === cityNorm)
                && (!regionNorm || !resolvedRegion || resolvedRegion === regionNorm)
                && (!countryNorm || !resolvedCountry || resolvedCountry === countryNorm);
        }) || resolvedItems.find((item) => {
            const address = item.address || {};
            const resolvedCity = normalizeText(address.city || address.province || address.state);
            const resolvedCountry = normalizeText(address.country);
            return (!cityNorm || resolvedCity === cityNorm)
                && (!countryNorm || !resolvedCountry || resolvedCountry === countryNorm);
        }) || resolvedItems[0];
    };

    const buildResolvedLocation = (matched, address = {}, originalQuery = '', coordinates = null) => {
        const addressTown = address.town || '';
        const matchedRegion = matched.region || '';
        const shouldKeepAddressTown = addressTown && normalizeText(addressTown) === normalizeText(matchedRegion);

        return {
            ...matched,
            displayRegion: shouldKeepAddressTown ? addressTown : matchedRegion,
            displayCity: address.province || matched.city || originalQuery,
            ...(coordinates ? { coordinates } : {}),
        };
    };

    const hydrateLocationFromCoordinates = async (location) => {
        if (!location?.coordinates) {
            return location;
        }

        const { latitude, longitude } = location.coordinates;
        const payload = await getReverseGeocode(latitude, longitude);
        const address = payload.address || {};
        const matched = await matchLocationFromAddress(address);

        if (matched) {
            return {
                ...matched,
                coordinates: location.coordinates,
            };
        }

        return {
            ...location,
            displayRegion: address.town || location.displayRegion || location.region,
            displayCity: address.province || location.displayCity || location.city,
        };
    };

    const matchLocationFromAddress = async (address) => {
        const candidates = unique([
            [address.province, address.country].filter(Boolean).join(' '),
            [address.city, address.country].filter(Boolean).join(' '),
            address.province,
            address.city,
            [address.town, address.province, address.country].filter(Boolean).join(' '),
            [address.city, address.state, address.country].filter(Boolean).join(' '),
            [address.town, address.country].filter(Boolean).join(' '),
            address.town,
            address.state,
        ]);

        for (const query of candidates) {
            let results = [];

            try {
                results = await searchLocations(query);
            } catch (error) {
                await debugLog('matchLocationFromAddress.search.error', {
                    query,
                    error: error?.message || String(error),
                });
                continue;
            }

            const matched = chooseBestLocation(results, address);

            if (matched) {
                return buildResolvedLocation(matched, address, query);
            }
        }

        return null;
    };

    const searchLocationsWithFallback = async (query) => {
        const directResults = await searchLocations(query);

        if (directResults.length) {
            return directResults;
        }

        const resolvedItems = await resolveLocationQuery(query);
        const enrichedResults = [];

        for (const item of resolvedItems) {
            const address = item.address || {};
            const matched = await matchLocationFromAddress(address);

            if (matched) {
                const latitude = Number(item.lat);
                const longitude = Number(item.lon);
                const coordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
                    ? { latitude, longitude }
                    : null;

                enrichedResults.push(buildResolvedLocation(matched, address, query, coordinates));
            }
        }

        return enrichedResults.filter((item, index, list) => list.findIndex((candidate) => candidate.id === item.id) === index);
    };

    const getQiblaAngle = (latitude, longitude) => {
        const lat1 = latitude * (Math.PI / 180);
        const lon1 = longitude * (Math.PI / 180);
        const lat2 = mecca.lat * (Math.PI / 180);
        const lon2 = mecca.lon * (Math.PI / 180);
        const deltaLon = lon2 - lon1;
        const y = Math.sin(deltaLon);
        const x = (Math.cos(lat1) * Math.tan(lat2)) - (Math.sin(lat1) * Math.cos(deltaLon));
        return (Math.atan2(y, x) * (180 / Math.PI) + 360) % 360;
    };

    const setQiblaPlaceholder = (message, visible = true) => {
        if (elements.qiblaMapStatus) {
            elements.qiblaMapStatus.textContent = message;
        }

        if (elements.qiblaMapPlaceholder) {
            elements.qiblaMapPlaceholder.hidden = !visible;
        }
    };

    const createQiblaDivIcon = (className) => {
        if (!window.L) {
            return null;
        }

        return window.L.divIcon({
            className: '',
            html: `<span class="${className}"></span>`,
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });
    };

    const ensureQiblaMap = () => {
        if (state.qiblaMap || !elements.qiblaMapCanvas || !window.L) {
            return state.qiblaMap;
        }

        state.qiblaMap = window.L.map(elements.qiblaMapCanvas, {
            zoomControl: false,
            attributionControl: true,
            dragging: true,
            scrollWheelZoom: true,
            doubleClickZoom: true,
            boxZoom: false,
            keyboard: false,
            tapHold: true,
        });

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(state.qiblaMap);

        state.qiblaLine = window.L.polyline([], {
            color: '#c19b12',
            weight: 4,
            opacity: 0.9,
            lineCap: 'round',
            dashArray: '12 10',
        }).addTo(state.qiblaMap);

        state.qiblaUserMarker = window.L.marker([0, 0], {
            icon: createQiblaDivIcon('qibla-user-marker'),
        }).bindTooltip('Siz', {
            permanent: true,
            direction: 'top',
            offset: [0, -12],
            className: 'qibla-tooltip',
        }).addTo(state.qiblaMap);

        state.qiblaMeccaMarker = window.L.marker([mecca.lat, mecca.lon], {
            icon: createQiblaDivIcon('qibla-mecca-marker'),
        }).bindTooltip('Mekke', {
            permanent: true,
            direction: 'top',
            offset: [0, -12],
            className: 'qibla-tooltip',
        }).addTo(state.qiblaMap);

        return state.qiblaMap;
    };

    const updateQiblaMap = (coordinates, locationLabel = '') => {
        if (!window.L) {
            setQiblaPlaceholder('Harita kütüphanesi yüklenemedi.');
            return;
        }

        const map = ensureQiblaMap();

        if (!map || !coordinates) {
            setQiblaPlaceholder('Konum koordinatı bulunamadı. Kıble haritası gösterilemiyor.');
            return;
        }

        const userLatLng = [coordinates.latitude, coordinates.longitude];
        const meccaLatLng = [mecca.lat, mecca.lon];

        state.qiblaUserMarker.setLatLng(userLatLng);
        state.qiblaMeccaMarker.setLatLng(meccaLatLng);
        state.qiblaLine.setLatLngs([userLatLng, meccaLatLng]);

        if (elements.qiblaMapFooter) {
            elements.qiblaMapFooter.textContent = locationLabel
                ? `${locationLabel} / ${config.qiblaDestination || 'Mekke, Suudi Arabistan'}`
                : (config.qiblaDestination || 'Mekke, Suudi Arabistan');
        }

        map.fitBounds(window.L.latLngBounds([userLatLng, meccaLatLng]), {
            padding: [28, 28],
            maxZoom: 5,
        });

        window.setTimeout(() => map.invalidateSize(), 50);
        setQiblaPlaceholder('', false);
    };

    const resolveCoordinatesForLocation = async (location) => {
        if (location?.coordinates) {
            return location.coordinates;
        }

        const cityNorm = normalizeText(location.displayCity || location.city);
        const regionNorm = normalizeText(location.displayRegion || location.region);
        const countryNorm = normalizeText(location.country);

        for (const query of getLocationLookupQueries(location)) {
            try {
                const results = await resolveLocationQuery(query);

                if (!results.length) {
                    continue;
                }

                const bestMatch = results.find((item) => {
                    const address = item.address || {};
                    const resolvedCity = normalizeText(address.city || address.province || address.state);
                    const resolvedRegion = normalizeText(address.town || address.city_district || address.county || address.suburb);
                    const resolvedCountry = normalizeText(address.country);
                    return (!cityNorm || resolvedCity === cityNorm)
                        && (!regionNorm || !resolvedRegion || resolvedRegion === regionNorm)
                        && (!countryNorm || !resolvedCountry || resolvedCountry === countryNorm);
                }) || results.find((item) => {
                    const address = item.address || {};
                    const resolvedCity = normalizeText(address.city || address.province || address.state);
                    const resolvedCountry = normalizeText(address.country);
                    return (!cityNorm || resolvedCity === cityNorm)
                        && (!countryNorm || !resolvedCountry || resolvedCountry === countryNorm);
                }) || results[0];

                const coordinates = toCoordinates(bestMatch);

                if (coordinates) {
                    return coordinates;
                }
            } catch (error) {
                await debugLog('resolveCoordinatesForLocation.query.error', {
                    query,
                    location,
                    error: error?.message || String(error),
                });
            }
        }

        return null;
    };

    const updateLocationLabels = (location) => {
        const displayLabel = formatDisplayLocation(location);
        const prayerLabel = formatResolvedPrayerLocation(location) || displayLabel;

        if (elements.heroLocation) {
            elements.heroLocation.textContent = prayerLabel;
        }

        if (elements.headerLocation) {
            elements.headerLocation.textContent = displayLabel;
        }
    };

    const updatePrayerCards = (times, currentPrayerKey, nextPrayerKey) => {
        prayerCards.forEach((card) => {
            const prayerKey = card.dataset.prayerKey;
            const apiKey = prayerMap[prayerKey];
            const time = times[apiKey];
            const timeNode = card.querySelector('.prayer-time');

            card.classList.toggle('is-active', prayerKey === currentPrayerKey);
            card.classList.toggle('next-prayertime', prayerKey === nextPrayerKey);

            if (timeNode && time) {
                timeNode.textContent = time;
            }
        });
    };

    const getCurrentPrayerInfo = (times, referenceDate = new Date()) => {
        const schedule = prayerOrder.map((key) => ({
            key,
            name: prayerNames[Object.keys(prayerMap).find((cardKey) => prayerMap[cardKey] === key)],
            time: times[key],
            date: parseTime(times[key], referenceDate),
        }));

        let currentIndex = 0;

        schedule.forEach((item, index) => {
            if (referenceDate >= item.date) {
                currentIndex = index;
            }
        });

        const current = schedule[currentIndex];
        const next = schedule[currentIndex + 1] || {
            key: schedule[0].key,
            date: parseTime(schedule[0].time, new Date(referenceDate.getFullYear(), referenceDate.getMonth(), referenceDate.getDate() + 1)),
            name: schedule[0].name,
            time: schedule[0].time,
        };

        return { current, next };
    };

    const updateCountdown = () => {
        if (!state.todayPrayerTimes) {
            return;
        }

        const nowDate = new Date();
        const { current, next } = getCurrentPrayerInfo(state.todayPrayerTimes, nowDate);
        const currentStart = current.date;
        const nextStart = next.date;
        const total = Math.max(1, nextStart.getTime() - currentStart.getTime());
        const remaining = Math.max(0, nextStart.getTime() - nowDate.getTime());
        const progress = 100 - Math.round((remaining / total) * 100);
        const hours = Math.floor(remaining / 3600000);
        const minutes = Math.floor((remaining % 3600000) / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        if (elements.countdownTime) {
            elements.countdownTime.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        if (elements.currentPrayerEyebrow) {
            elements.currentPrayerEyebrow.textContent = `Sonraki Vakit: ${next.name}`;
        }

        if (elements.currentPrayerName) {
            elements.currentPrayerName.textContent = next.time;
        }

        if (elements.currentPrayerWindow) {
            elements.currentPrayerWindow.textContent = `Başlangıç ${current.time} • Bitiş ${next.time}`;
        }

        if (elements.countdownRingProgress) {
            const circumference = Number.parseFloat(elements.countdownRingProgress.dataset.circumference || '263.89');
            const offset = circumference - ((Math.max(0, Math.min(100, progress)) / 100) * circumference);
            elements.countdownRingProgress.style.strokeDasharray = `${circumference}`;
            elements.countdownRingProgress.style.strokeDashoffset = `${offset}`;
        }

        const currentPrayerCardKey = Object.keys(prayerMap).find((key) => prayerMap[key] === current.key);
        const nextPrayerCardKey = Object.keys(prayerMap).find((key) => prayerMap[key] === next.key);
        updatePrayerCards(state.todayPrayerTimes, currentPrayerCardKey, nextPrayerCardKey);
    };

    const updateSunJourney = (times) => {
        const nowDate = new Date();
        const sunrise = parseTime(times.sun, nowDate);
        const sunset = parseTime(times.maghrib, nowDate);
        const dayLength = Math.max(1, sunset.getTime() - sunrise.getTime());
        let percent = 0;

        if (nowDate < sunrise) {
            percent = 0;
        } else if (nowDate > sunset) {
            percent = 0;
        } else {
            const elapsed = nowDate.getTime() - sunrise.getTime();
            percent = Math.round((elapsed / dayLength) * 100);
        }

        if (elements.sunJourneyValue) {
            elements.sunJourneyValue.textContent = `${percent}%`;
        }

        if (elements.sunriseBar) {
            elements.sunriseBar.style.height = `${Math.max(8, Math.min(100, percent))}%`;
        }

        if (elements.sunsetBar) {
            elements.sunsetBar.style.height = `${Math.max(8, 100 - percent)}%`;
        }
    };

    const renderMonthlySchedule = () => {
        if (!elements.monthlyScheduleTableBody) {
            return;
        }

        elements.monthlyScheduleTableBody.innerHTML = '';

        if (!state.monthPrayerTimes.length) {
            return;
        }

        const todayIso = formatDateKey(new Date());

        const rows = state.monthPrayerTimes.map((item) => {
            const date = parseApiDate(item.date);
            const itemDateKey = typeof item.date === 'string' ? item.date.slice(0, 10) : '';
            const isToday = itemDateKey === todayIso;
            return `
                <tr${isToday ? ' class="is-today"' : ''}>
                    <td>${formatDate(date)}</td>
                    <td>${item.fajr}</td>
                    <td>${item.sun}</td>
                    <td>${item.dhuhr}</td>
                    <td>${item.asr}</td>
                    <td>${item.maghrib}</td>
                    <td>${item.isha}</td>
                </tr>
            `;
        }).join('');

        elements.monthlyScheduleTableBody.innerHTML = rows;
    };

    const applyPrayerTimes = (times) => {
        state.todayPrayerTimes = times;
        updatePrayerCards(times);
        updateCountdown();
        updateSunJourney(times);

        if (state.countdownTimer) {
            window.clearInterval(state.countdownTimer);
        }

        state.countdownTimer = window.setInterval(() => {
            updateCountdown();
        }, 1000);

        window.setInterval(() => {
            updateSunJourney(state.todayPrayerTimes);
        }, 30000);
    };

    const refreshSchedulePanel = async () => {
        renderMonthlySchedule();

        if (elements.scheduleStatus) {
            elements.scheduleStatus.textContent = '';
        }
    };

    const selectLocation = async (location, source, coordinates = null) => {
        await debugLog('selectLocation.start', { source, location, coordinates });
        const initialLocation = coordinates ? { ...location, coordinates } : location;
        let resolvedCoordinates = coordinates || null;

        if (!resolvedCoordinates) {
            try {
                resolvedCoordinates = await resolveCoordinatesForLocation(initialLocation);
            } catch (error) {
                await debugLog('selectLocation.resolveCoordinates.error', {
                    source,
                    location: initialLocation,
                    error: error?.message || String(error),
                });
                resolvedCoordinates = null;
            }
        }

        const resolvedLocation = resolvedCoordinates ? { ...initialLocation, coordinates: resolvedCoordinates } : initialLocation;
        state.selectedLocation = resolvedLocation;
        updateLocationLabels(resolvedLocation);
        setManualVisible(false);
        setStatus(config.status?.searching || 'Vakitler getiriliyor...', 'neutral');

        if (resolvedCoordinates) {
            updateCompass(getQiblaAngle(resolvedCoordinates.latitude, resolvedCoordinates.longitude));
            updateQiblaMap(resolvedCoordinates, formatDisplayLocation(resolvedLocation));
        } else {
            setQiblaPlaceholder('Bu konum için koordinat çözümlenemedi. Harita gösterilemiyor.');
        }

        try {
            const monthly = await getPrayerTimes(resolvedLocation.id);
            state.monthPrayerTimes = monthly;
            await debugLog('selectLocation.prayertimes.loaded', {
                locationId: resolvedLocation.id,
                count: monthly.length,
            });
            const todayIso = formatDateKey(new Date());
            const today = monthly.find((item) => item.date.slice(0, 10) === todayIso) || monthly[0];

            if (!today) {
                throw new Error('today-not-found');
            }

            applyPrayerTimes(today);
            renderMonthlySchedule();

            saveLocation(resolvedLocation);

            const readyMessage = source === 'manual'
                ? `${formatDisplayLocation(resolvedLocation)} için namaz vakitleri güncellendi.`
                : source === 'ip'
                    ? config.status?.ip_ready || 'Yaklaşık konum IP adresinden belirlendi ve vakitler güncellendi.'
                    : config.status?.ready || 'Bugünün namaz vakitleri güncel.';

            setStatus(readyMessage, 'success');
            await debugLog('selectLocation.success', {
                source,
                location: resolvedLocation,
                todayFound: Boolean(today),
            });

            if (!elements.schedulePanel?.hidden) {
                await refreshSchedulePanel();
            }
        } catch (error) {
            logError('Namaz vakitleri alinamadi:', error);
            await debugLog('selectLocation.exception', {
                source,
                error: error?.message || String(error),
                location: resolvedLocation,
            });
            setStatus(getErrorMessage(error, config.status?.api_error || 'Namaz vakitleri alınamadı.'), 'error');
            setManualVisible(true);
        }
    };

    const resolveLocationFromGeolocation = async (position) => {
        const { latitude, longitude } = position.coords;
        await debugLog('geolocation.received', { latitude, longitude });
        const payload = await getReverseGeocode(latitude, longitude);
        const address = payload.address || {};
        await debugLog('geolocation.reverse.success', { address });
        const location = await matchLocationFromAddress(address);

        if (!location) {
            throw new Error('location-not-found');
        }

        await selectLocation(location, 'geolocation', { latitude, longitude });
    };

    const resolveLocationFromIp = async () => {
        setStatus(config.status?.ip_lookup || 'Konum izni yok. IP adresinden yaklaşık konum tespit ediliyor.', 'neutral');

        const payload = await getIpGeolocation();
        await debugLog('ip.lookup.success', payload);
        const city = payload.city || '';
        const region = payload.region || '';
        const country = payload.country_name || payload.country || '';
        const latitude = Number(payload.latitude);
        const longitude = Number(payload.longitude);
        const coordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
            ? { latitude, longitude }
            : null;
        const queries = unique([
            [city, region, country].filter(Boolean).join(' ').trim(),
            [city, country].filter(Boolean).join(' ').trim(),
            [city, region].filter(Boolean).join(' ').trim(),
            city,
        ]);

        if (!queries.length) {
            throw new Error('ip-location-not-found');
        }

        let results = [];

        for (const query of queries) {
            results = await searchLocations(query);

            if (results.length) {
                break;
            }
        }

        if (!results.length) {
            throw new Error('ip-location-search-empty');
        }

        const matched = chooseBestLocation(results, {
            city,
            state: region,
            country,
        });

        if (!matched) {
            throw new Error('ip-location-match-failed');
        }

        await selectLocation(matched, 'ip', coordinates);
    };

    const requestUserLocation = () => {
        void debugLog('requestUserLocation.start', {
            geolocationSupported: Boolean(navigator.geolocation),
        });
        if (!navigator.geolocation) {
            resolveLocationFromIp()
                .catch(() => {
                    setStatus(config.status?.ip_not_found || 'IP adresinden konum tespit edilemedi. Aşağıdan şehir veya ilçe seçebilirsiniz.', 'warning');
                    setManualVisible(true);
                })
                .finally(() => {
                    setLoadingState(elements.geoLocateButton, false, config.search?.button || 'Konumumu Bul');
                });
            return;
        }

        setLoadingState(elements.geoLocateButton, true, 'Konum alınıyor...');
        setStatus(config.status?.loading || 'Konum alınıyor...', 'neutral');

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    await resolveLocationFromGeolocation(position);
                } catch (error) {
                    await debugLog('geolocation.exception', {
                        error: error?.message || String(error),
                    });
                    try {
                        await resolveLocationFromIp();
                    } catch (ipError) {
                        await debugLog('ip.lookup.exception.after-geolocation', {
                            error: ipError?.message || String(ipError),
                        });
                        setStatus(config.status?.ip_not_found || 'IP adresinden konum tespit edilemedi. Aşağıdan şehir veya ilçe seçebilirsiniz.', 'warning');
                        setManualVisible(true);
                    }
                } finally {
                    setLoadingState(elements.geoLocateButton, false, config.search?.button || 'Konumumu Bul');
                }
            },
            () => {
                void debugLog('geolocation.denied');
                setStatus(config.status?.permission_denied || 'Konum izni verilmedi.', 'warning');
                resolveLocationFromIp()
                    .catch(() => {
                        void debugLog('ip.lookup.exception.after-denied');
                        setStatus(config.status?.ip_not_found || 'IP adresinden konum tespit edilemedi. Aşağıdan şehir veya ilçe seçebilirsiniz.', 'warning');
                        setManualVisible(true);
                    })
                    .finally(() => {
                        setLoadingState(elements.geoLocateButton, false, config.search?.button || 'Konumumu Bul');
                    });
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 300000,
            }
        );
    };

    const searchAndRender = async (query) => {
        if (query.length < 2) {
            clearSearchResults();
            return;
        }

        setStatus(config.status?.searching || 'Konum aranıyor...', 'neutral');

        try {
            const results = await searchLocationsWithFallback(query);
            renderSearchResults(results.slice(0, 8));
        } catch (error) {
            logError('Konum aramasi basarisiz:', error);
            setStatus(getErrorMessage(error, config.status?.api_error || 'Arama yapılamadı.'), 'error');
        }
    };

    const handleManualSearchSubmit = async (event) => {
        event.preventDefault();
        const query = elements.searchInput?.value.trim() || '';

        if (query.length < 2) {
            elements.searchInput?.focus();
            return;
        }

        try {
            const results = await searchLocationsWithFallback(query);
            renderSearchResults(results.slice(0, 8));

            if (results.length >= 1) {
                const bestMatch = chooseBestManualLocation(results, query);

                if (!bestMatch) {
                    return;
                }

                clearSearchResults();
                if (elements.searchInput) {
                    elements.searchInput.value = formatDisplayLocation(bestMatch);
                }
                await selectLocation(bestMatch, 'manual');
            }
        } catch (error) {
            logError('Elle konum aramasi basarisiz:', error);
            setStatus(getErrorMessage(error, config.status?.api_error || 'Arama yapılamadı.'), 'error');
        }
    };

    const toggleSchedulePanel = async () => {
        if (!elements.schedulePanel || !elements.scheduleToggleButton) {
            return;
        }

        const willOpen = elements.schedulePanel.hidden;
        elements.schedulePanel.hidden = !willOpen;
        elements.scheduleToggleButton.setAttribute('aria-expanded', String(willOpen));

        if (willOpen) {
            await refreshSchedulePanel();
        }
    };

    const bootstrapLocation = async () => {
        await debugLog('bootstrap.start');
        updateClock();
        window.setInterval(updateClock, 1000);
        updateCompass(Number(config.defaultQiblaAngle || 152.9));
        initDeviceCompass();
        setQiblaPlaceholder('Konum alındığında kıble haritası burada görünecek.');

        const stored = loadStoredLocation();

        if (stored?.id) {
            try {
                await debugLog('bootstrap.storage.found', stored);
                const hydratedStored = stored.version === storageVersion
                    ? stored
                    : await hydrateLocationFromCoordinates(stored);

                await debugLog('bootstrap.storage.hydrated', hydratedStored);
                await selectLocation(hydratedStored, 'storage', hydratedStored.coordinates || null);

                return;
            } catch (error) {
                await debugLog('bootstrap.storage.exception', {
                    error: error?.message || String(error),
                });
                localStorage.removeItem(storageKey);
            }
        }

        requestUserLocation();
    };

    elements.searchForm?.addEventListener('submit', handleManualSearchSubmit);
    elements.searchInput?.addEventListener('input', (event) => {
        const query = event.target.value.trim();
        window.clearTimeout(elements.searchInput._debounceTimer);
        elements.searchInput._debounceTimer = window.setTimeout(() => searchAndRender(query), 260);
    });
    elements.geoLocateButton?.addEventListener('click', requestUserLocation);
    elements.changeLocationButton?.addEventListener('click', () => {
        setManualVisible(true);
        elements.searchInput?.focus();
        setStatus('Yeni konum seçmek için şehir veya ilçe arayabilirsiniz.', 'neutral');
    });
    elements.scheduleToggleButton?.addEventListener('click', toggleSchedulePanel);

    document.addEventListener('click', (event) => {
        if (!elements.searchResults || !elements.searchForm) {
            return;
        }

        if (!elements.searchForm.contains(event.target)) {
            clearSearchResults();
        }
    });

    bootstrapLocation();
});
