// Global variables
let map;
let hops = new Map();
let connections = new Map();
let overlays = [];
let currentMode = 'view';
let temporaryMarker = null;
let boundaries = null;
let boundaryRectangle = null;
let overlayCorners = [];
let overlayImage = null;
let navigationMode = false;
let navigationFromCurrent = false;
let navigationPath = null;
let connectionMode = false;
let firstHop = null;
let temporaryLine = null;
let currentLocationMarker = null;
let currentLocation = 'main-campus';

// Initialize map
function initMap(locationCode = 'main-campus') {
    currentLocation = locationCode;

    map = L.map('map').setView([51.505, -0.09], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    map.on('mousemove', function(e) {
        const coordsDisplay = document.getElementById('coordinatesDisplay');
        if (coordsDisplay) {
            coordsDisplay.innerHTML = `Lat: ${e.latlng.lat.toFixed(6)}<br>Lng: ${e.latlng.lng.toFixed(6)}`;
        }
    });

    loadMapData();
    setupEventListeners();
}

function setupEventListeners() {
    const roadWidthSlider = document.getElementById('roadWidth');
    if (roadWidthSlider) {
        roadWidthSlider.addEventListener('input', function(e) {
            const display = document.getElementById('roadWidthDisplay');
            if (display) display.textContent = e.target.value;
        });
    }

    const overlayOpacitySlider = document.getElementById('overlayOpacity');
    if (overlayOpacitySlider) {
        overlayOpacitySlider.addEventListener('input', function(e) {
            const display = document.getElementById('opacityDisplay');
            if (display) display.textContent = e.target.value + '%';
            if (overlayImage) {
                overlayImage.setOpacity(e.target.value / 100);
            }
        });
    }

    const overlayImageInput = document.getElementById('overlayImage');
    if (overlayImageInput) {
        overlayImageInput.addEventListener('change', handleImageUpload);
    }

    const markerImageInput = document.getElementById('markerImage');
    if (markerImageInput) {
        markerImageInput.addEventListener('change', handleMarkerImageUpload);
    }
}

function handleImageUpload(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            showImagePreview(e.target.result, 'overlayPreview');
        };
        reader.readAsDataURL(file);
    }
}

function handleMarkerImageUpload(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            showImagePreview(e.target.result, 'markerPreview');
        };
        reader.readAsDataURL(file);
    }
}

function showImagePreview(src, containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `<img src="${src}" alt="Preview" style="max-width: 100%; max-height: 100px; border-radius: 4px;">`;
        container.style.display = 'block';
    }
}

async function loadMapData() {
    try {
        showStatus('Loading map data...', 'info');
        const response = await fetch(`/api/${currentLocation}/map-data`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // Clear existing data
        hops.forEach(hop => map.removeLayer(hop));
        connections.forEach(connection => map.removeLayer(connection));
        overlays.forEach(overlay => map.removeLayer(overlay));
        hops.clear();
        connections.clear();
        overlays = [];

        if (data.boundary) {
            setBoundaries(data.boundary);
        }

        if (data.hops && Array.isArray(data.hops)) {
            data.hops.forEach(hopData => {
                try {
                    addHopToMap(hopData);
                } catch (error) {
                    console.error('Error adding hop to map:', error, hopData);
                }
            });
        }

        if (data.connections && Array.isArray(data.connections)) {
            console.log('Loading connections:', data.connections.length);
            data.connections.forEach(connectionData => {
                try {
                    addConnectionToMap(connectionData);
                } catch (error) {
                    console.error('Error adding connection to map:', error, connectionData);
                }
            });
        }

        if (data.overlays && Array.isArray(data.overlays)) {
            data.overlays.forEach(overlayData => {
                try {
                    addOverlayToMap(overlayData);
                } catch (error) {
                    console.error('Error adding overlay to map:', error, overlayData);
                }
            });
        }

        updateHopList();
        showStatus(`Map loaded: ${hops.size} hops, ${connections.size} connections`, 'success');

    } catch (error) {
        console.error('Error loading map data:', error);
        showStatus('Failed to load map data: ' + error.message, 'error');
    }
}

async function searchLocation() {
    const query = document.getElementById('searchInput')?.value;
    if (!query) return;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
        const data = await response.json();

        if (data.length > 0) {
            const result = data[0];
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);

            if (boundaries && !isWithinBoundaries(lat, lng)) {
                showStatus('Location is outside campus boundaries', 'error');
                return;
            }

            map.setView([lat, lng], 18);

            if (temporaryMarker) {
                map.removeLayer(temporaryMarker);
            }
            temporaryMarker = L.marker([lat, lng]).addTo(map);
            temporaryMarker.bindPopup(result.display_name).openPopup();

            showStatus('Location found!', 'success');
        } else {
            showStatus('Location not found', 'error');
        }
    } catch (error) {
        console.error('Search error:', error);
        showStatus('Search failed', 'error');
    }
}

function startBoundarySelection() {
    currentMode = 'boundary';
    showMode('Select First Corner');
    map.once('click', function(e1) {
        const corner1 = e1.latlng;
        showMode('Select Second Corner');
        map.once('click', function(e2) {
            const corner2 = e2.latlng;
            const bounds = L.latLngBounds([corner1, corner2]);
            saveBoundaries(bounds);
            currentMode = 'view';
            hideMode();
        });
    });
}

async function saveBoundaries(bounds) {
    const boundaryData = {
        north: bounds.getNorth(),
        south: bounds.getSouth(),
        east: bounds.getEast(),
        west: bounds.getWest()
    };

    try {
        const response = await fetch(`/api/${currentLocation}/boundary`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(boundaryData)
        });

        if (response.ok) {
            setBoundaries(boundaryData);
            showStatus('Boundaries set successfully', 'success');
        }
    } catch (error) {
        console.error('Error saving boundaries:', error);
        showStatus('Failed to save boundaries', 'error');
    }
}

function setBoundaries(boundaryData) {
    boundaries = boundaryData;

    if (boundaryRectangle) {
        map.removeLayer(boundaryRectangle);
    }

    const bounds = [[boundaries.south, boundaries.west], [boundaries.north, boundaries.east]];
    boundaryRectangle = L.rectangle(bounds, {
        color: '#ff0000',
        weight: 2,
        opacity: 0.5,
        fillOpacity: 0.1,
        dashArray: '5, 5'
    }).addTo(map);

    map.setMaxBounds(bounds);
    map.fitBounds(bounds);

    const boundaryInfo = document.getElementById('boundaryInfo');
    if (boundaryInfo) {
        boundaryInfo.style.display = 'block';
        boundaryInfo.innerHTML = `
            N: ${boundaries.north.toFixed(6)}<br>
            S: ${boundaries.south.toFixed(6)}<br>
            E: ${boundaries.east.toFixed(6)}<br>
            W: ${boundaries.west.toFixed(6)}
        `;
    }
}

function clearBoundaries() {
    if (boundaryRectangle) {
        map.removeLayer(boundaryRectangle);
        boundaryRectangle = null;
    }
    boundaries = null;
    map.setMaxBounds(null);
    const boundaryInfo = document.getElementById('boundaryInfo');
    if (boundaryInfo) boundaryInfo.style.display = 'none';
    showStatus('Boundaries cleared', 'info');
}

function isWithinBoundaries(lat, lng) {
    if (!boundaries) return true;
    return lat >= boundaries.south && lat <= boundaries.north &&
           lng >= boundaries.west && lng <= boundaries.east;
}

function startAddingMarker() {
    currentMode = 'marker';
    showMode('Click to Place Marker');
    map.once('click', function(e) {
        if (!isWithinBoundaries(e.latlng.lat, e.latlng.lng)) {
            showStatus('Cannot place marker outside boundaries', 'error');
            currentMode = 'view';
            hideMode();
            return;
        }

        showMarkerDialog(e.latlng.lat, e.latlng.lng);
        currentMode = 'view';
        hideMode();
    });
}

function showMarkerDialog(lat, lng) {
    const name = prompt('Enter marker name:');
    if (!name) return;

    const description = prompt('Enter description (optional):') || '';

    const markerData = {
        type: 'marker',
        name: name,
        description: description,
        latitude: lat,
        longitude: lng
    };

    const imageInput = document.getElementById('markerImage');
    if (imageInput && imageInput.files[0]) {
        saveHopWithImage(markerData, imageInput.files[0]);
        imageInput.value = '';
        const preview = document.getElementById('markerPreview');
        if (preview) preview.style.display = 'none';
    } else {
        saveHop(markerData);
    }
}

function startAddingHop() {
    currentMode = 'hop';
    showMode('Click to Place Hop');
    map.once('click', function(e) {
        if (!isWithinBoundaries(e.latlng.lat, e.latlng.lng)) {
            showStatus('Cannot place hop outside boundaries', 'error');
            currentMode = 'view';
            hideMode();
            return;
        }

        saveHop({
            type: 'hop',
            name: `Hop ${hops.size + 1}`,
            latitude: e.latlng.lat,
            longitude: e.latlng.lng
        });
        currentMode = 'view';
        hideMode();
    });
}

async function saveHop(hopData) {
    try {
        const response = await fetch(`/api/${currentLocation}/hops`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(hopData)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            addHopToMap(data.hop);
            updateHopList();
            showStatus(`${hopData.type === 'marker' ? 'Marker' : 'Hop'} added successfully`, 'success');
        } else {
            showStatus(data.error || 'Failed to save', 'error');
        }
    } catch (error) {
        console.error('Error saving hop:', error);
        showStatus('Failed to save', 'error');
    }
}

async function saveHopWithImage(hopData, imageFile) {
    try {
        const formData = new FormData();
        Object.keys(hopData).forEach(key => {
            formData.append(key, hopData[key]);
        });
        formData.append('image', imageFile);

        const response = await fetch(`/api/${currentLocation}/hops`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });

        const data = await response.json();

        if (response.ok && data.success) {
            addHopToMap(data.hop);
            updateHopList();
            showStatus(`${hopData.type === 'marker' ? 'Marker' : 'Hop'} with image added successfully`, 'success');
        } else {
            showStatus(data.error || 'Failed to save with image', 'error');
        }
    } catch (error) {
        console.error('Error saving hop with image:', error);
        showStatus('Failed to save with image', 'error');
    }
}

function addHopToMap(hopData) {
    let hopElement;

    if (hopData.type === 'marker') {
        hopElement = L.marker([hopData.latitude, hopData.longitude], {
            zIndexOffset: 1000
        });

        let popupContent = `<div class="popup-content">
            <h4>${hopData.name}</h4>
            ${hopData.description ? `<p>${hopData.description.substring(0, 100)}${hopData.description.length > 100 ? '...' : ''}</p>` : ''}
            ${hopData.image_path || hopData.description ?
                `<button class="btn btn-info" onclick="showMarkerDetails(${hopData.id})" style="margin-top: 8px; padding: 6px 12px; font-size: 12px;">📋 Display Details</button>` :
                ''
            }
        </div>`;

        hopElement.bindPopup(popupContent);
    } else {
        hopElement = L.circleMarker([hopData.latitude, hopData.longitude], {
            radius: 8,
            fillColor: '#3388ff',
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.9,
            zIndexOffset: 1000
        }).bindPopup(`<b>${hopData.name}</b>`);
    }

    hopElement.addTo(map);
    hopElement.hopData = hopData;

    hopElement.on('click', function(e) {
        L.DomEvent.stopPropagation(e);
        if (navigationMode) {
            handleNavigationClick(hopData);
        } else if (connectionMode) {
            handleConnectionClick(hopData);
        }
    });

    hops.set(hopData.id, hopElement);
}

function updateHopList() {
    const list = document.getElementById('hopList');
    if (!list) return;

    list.innerHTML = '';

    hops.forEach((hop, id) => {
        const item = document.createElement('div');
        item.className = 'list-item';

        const type = hop.hopData.type === 'marker' ? '📍' : '•';
        const imageHtml = hop.hopData.image_path ?
            `<img src="/storage/${hop.hopData.image_path}" class="list-item-image" alt="${hop.hopData.name}">` : '';

        item.innerHTML = `
            <div class="list-item-content">
                ${imageHtml}
                <div class="list-item-text">
                    <div class="list-item-name">${type} ${hop.hopData.name}</div>
                    ${hop.hopData.description ? `<div class="list-item-description">${hop.hopData.description.substring(0, 50)}${hop.hopData.description.length > 50 ? '...' : ''}</div>` : ''}
                </div>
            </div>
            <div class="list-item-actions">
                ${hop.hopData.image_path || hop.hopData.description ?
                    `<button class="btn btn-info" style="padding: 3px 6px; font-size: 11px; margin-right: 5px;" onclick="showMarkerDetails(${id})">👁️</button>` :
                    ''
                }
                <button class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;" onclick="removeHop(${id})">Remove</button>
            </div>
        `;
        list.appendChild(item);
    });
}

async function removeHop(hopId) {
    if (!confirm('Are you sure you want to remove this hop?')) return;

    try {
        const response = await fetch(`/api/${currentLocation}/hops/${hopId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            const hop = hops.get(hopId);
            if (hop) {
                map.removeLayer(hop);
                hops.delete(hopId);
                updateHopList();

                connections.forEach((connection, id) => {
                    if (connection.connectionData.hop_from_id === hopId ||
                        connection.connectionData.hop_to_id === hopId) {
                        map.removeLayer(connection);
                        connections.delete(id);
                    }
                });

                showStatus('Hop removed', 'success');
            }
        }
    } catch (error) {
        console.error('Error removing hop:', error);
        showStatus('Failed to remove hop', 'error');
    }
}

function clearAllHops() {
    if (confirm('Are you sure you want to remove all hops and connections?')) {
        hops.forEach(hop => map.removeLayer(hop));
        connections.forEach(connection => map.removeLayer(connection));
        hops.clear();
        connections.clear();
        updateHopList();
        showStatus('All hops cleared', 'info');
    }
}

function startConnectingHops() {
    connectionMode = true;
    firstHop = null;
    showMode('Select First Hop', 'connection-mode');
    const finishBtn = document.getElementById('finishConnectionBtn');
    if (finishBtn) finishBtn.style.display = 'inline-block';
}

function handleConnectionClick(hopData) {
    if (!firstHop) {
        firstHop = hopData;
        showMode('Select Second Hop', 'connection-mode');
        map.on('mousemove', drawTemporaryLine);
    } else {
        createConnection(firstHop, hopData);
        finishConnection();
    }
}

function drawTemporaryLine(e) {
    if (!firstHop) return;

    if (temporaryLine) {
        map.removeLayer(temporaryLine);
    }

    temporaryLine = L.polyline([
        [firstHop.latitude, firstHop.longitude],
        [e.latlng.lat, e.latlng.lng]
    ], {
        color: document.getElementById('roadColor')?.value || '#0066cc',
        weight: parseInt(document.getElementById('roadWidth')?.value || 5),
        opacity: 0.5,
        dashArray: '5, 5'
    }).addTo(map);
}

async function createConnection(hop1, hop2) {
    if (hop1.id === hop2.id) {
        showStatus('Cannot connect hop to itself', 'error');
        return;
    }

    const connectionData = {
        hop_from_id: hop1.id,
        hop_to_id: hop2.id,
        name: `${hop1.name} - ${hop2.name}`,
        width: parseInt(document.getElementById('roadWidth')?.value || 5),
        color: document.getElementById('roadColor')?.value || '#0066cc'
    };

    try {
        const response = await fetch(`/api/${currentLocation}/connections`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(connectionData)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            addConnectionToMap(data.connection);
            showStatus('Connection created', 'success');
        } else {
            showStatus(data.error || 'Failed to create connection', 'error');
        }
    } catch (error) {
        console.error('Error creating connection:', error);
        showStatus('Failed to create connection', 'error');
    }
}

function addConnectionToMap(connectionData) {
    let fromHop = connectionData.hop_from;
    let toHop = connectionData.hop_to;

    if (!fromHop && connectionData.hop_from_id) {
        const fromHopElement = hops.get(connectionData.hop_from_id);
        fromHop = fromHopElement ? fromHopElement.hopData : null;
    }

    if (!toHop && connectionData.hop_to_id) {
        const toHopElement = hops.get(connectionData.hop_to_id);
        toHop = toHopElement ? toHopElement.hopData : null;
    }

    if (!fromHop || !toHop) {
        console.warn('Skipping connection - missing hop data:', connectionData);
        return;
    }

    const connection = L.polyline([
        [fromHop.latitude, fromHop.longitude],
        [toHop.latitude, toHop.longitude]
    ], {
        color: connectionData.color || '#0066cc',
        weight: connectionData.width || 5,
        opacity: 0.8,
        interactive: false
    }).addTo(map);

    connection.connectionData = connectionData;
    connections.set(connectionData.id, connection);
}

function finishConnection() {
    connectionMode = false;
    firstHop = null;
    map.off('mousemove', drawTemporaryLine);
    if (temporaryLine) {
        map.removeLayer(temporaryLine);
        temporaryLine = null;
    }
    const finishBtn = document.getElementById('finishConnectionBtn');
    if (finishBtn) finishBtn.style.display = 'none';
    hideMode();
}

function startOverlayPlacement() {
    const fileInput = document.getElementById('overlayImage');
    if (!fileInput?.files || !fileInput.files[0]) {
        showStatus('Please select an image file first', 'error');
        return;
    }

    overlayCorners = [];
    currentMode = 'overlay';
    showMode('Click NW Corner');

    map.on('click', overlayCornerHandler);
}

function overlayCornerHandler(e) {
    if (overlayCorners.length >= 4) return;

    overlayCorners.push([e.latlng.lat, e.latlng.lng]);

    L.circleMarker(e.latlng, {
        radius: 8,
        color: '#ff0000',
        fillOpacity: 0.8
    }).addTo(map);

    const cornerNames = ['NW', 'NE', 'SE', 'SW'];
    if (overlayCorners.length < 4) {
        showMode(`Click ${cornerNames[overlayCorners.length]} Corner`);
    } else {
        map.off('click', overlayCornerHandler);
        placeOverlay();
    }
}

async function placeOverlay() {
    const fileInput = document.getElementById('overlayImage');
    const file = fileInput.files[0];
    const name = prompt('Enter overlay name:');

    if (!name) {
        overlayCorners = [];
        currentMode = 'view';
        hideMode();
        map.eachLayer(layer => {
            if (layer instanceof L.CircleMarker && layer.options.color === '#ff0000') {
                map.removeLayer(layer);
            }
        });
        return;
    }

    const formData = new FormData();
    formData.append('name', name);
    formData.append('image', file);
    formData.append('corners', JSON.stringify(overlayCorners));
    formData.append('opacity', (document.getElementById('overlayOpacity')?.value || 70) / 100);

    try {
        const response = await fetch(`/api/${currentLocation}/overlay`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });

        if (response.ok) {
            const data = await response.json();

            map.eachLayer(layer => {
                if (layer instanceof L.CircleMarker && layer.options.color === '#ff0000') {
                    map.removeLayer(layer);
                }
            });

            addOverlayToMap(data.overlay);
            showStatus('Overlay added successfully', 'success');
        } else {
            showStatus('Failed to save overlay', 'error');
        }
    } catch (error) {
        console.error('Error saving overlay:', error);
        showStatus('Failed to save overlay', 'error');
    }

    overlayCorners = [];
    currentMode = 'view';
    hideMode();
    fileInput.value = '';
    const preview = document.getElementById('overlayPreview');
    if (preview) preview.style.display = 'none';
}

function addOverlayToMap(overlayData) {
    const imageUrl = `/storage/${overlayData.image_path}`;
    const corners = overlayData.corners;

    const bounds = L.latLngBounds([
        [corners[2][0], corners[3][1]],
        [corners[0][0], corners[1][1]]
    ]);

    overlayImage = L.imageOverlay(imageUrl, bounds, {
        opacity: overlayData.opacity,
        interactive: false
    }).addTo(map);

    overlays.push(overlayImage);
}

function startNavigation() {
    navigationMode = true;
    navigationFromCurrent = false;
    const navInfo = document.getElementById('navigationInfo');
    if (navInfo) {
        navInfo.style.display = 'block';
        navInfo.innerHTML = 'Click on destination marker';
    }
    showStatus('Click on a marker to navigate to it', 'info');
}

function navigateFromCurrent() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (!isWithinBoundaries(lat, lng)) {
                    showStatus('Your location is outside campus boundaries', 'error');
                    return;
                }

                showCurrentLocation(lat, lng);
                navigationMode = true;
                navigationFromCurrent = true;
                const navInfo = document.getElementById('navigationInfo');
                if (navInfo) {
                    navInfo.style.display = 'block';
                    navInfo.innerHTML = 'Click on destination marker';
                }
                showStatus('Click on a marker to navigate from your location', 'info');
            },
            error => {
                const center = map.getCenter();
                showCurrentLocation(center.lat, center.lng);
                navigationMode = true;
                navigationFromCurrent = true;
                const navInfo = document.getElementById('navigationInfo');
                if (navInfo) {
                    navInfo.style.display = 'block';
                    navInfo.innerHTML = 'Click on destination marker';
                }
                showStatus('Using map center as current location. Click destination marker.', 'info');
            }
        );
    } else {
        const center = map.getCenter();
        showCurrentLocation(center.lat, center.lng);
        navigationMode = true;
        navigationFromCurrent = true;
        const navInfo = document.getElementById('navigationInfo');
        if (navInfo) {
            navInfo.style.display = 'block';
            navInfo.innerHTML = 'Click on destination marker';
        }
        showStatus('Using map center as current location. Click destination marker.', 'info');
    }
}

function showCurrentLocation(lat, lng) {
    if (currentLocationMarker) {
        map.removeLayer(currentLocationMarker);
    }

    currentLocationMarker = L.marker([lat, lng], {
        icon: L.icon({
            iconUrl: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNmZjAwMDAiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIvPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjMiIGZpbGw9IiNmZjAwMDAiLz48L3N2Zz4=',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        })
    }).addTo(map);

    currentLocationMarker.bindPopup('Your Location').openPopup();
}

async function handleNavigationClick(destinationHop) {
    if (!navigationMode) return;

    let startId = null;
    let currentLat = null;
    let currentLng = null;

    if (navigationFromCurrent && currentLocationMarker) {
        const pos = currentLocationMarker.getLatLng();
        currentLat = pos.lat;
        currentLng = pos.lng;
    } else {
        const startHop = prompt('Enter the name of your starting marker:');
        if (!startHop) {
            navigationMode = false;
            const navInfo = document.getElementById('navigationInfo');
            if (navInfo) navInfo.style.display = 'none';
            return;
        }

        let found = false;
        hops.forEach(hop => {
            if (hop.hopData.name.toLowerCase().includes(startHop.toLowerCase())) {
                startId = hop.hopData.id;
                found = true;
            }
        });

        if (!found) {
            showStatus('Starting marker not found', 'error');
            return;
        }
    }

    const path = await findPath(startId, destinationHop.id, currentLat, currentLng);

    navigationMode = false;
    navigationFromCurrent = false;
    const navInfo = document.getElementById('navigationInfo');
    if (navInfo) navInfo.style.display = 'none';
}

async function findPath(startId, endId, currentLat = null, currentLng = null) {
    try {
        const requestData = {
            end_id: endId
        };

        if (startId) {
            requestData.start_id = startId;
        } else if (currentLat && currentLng) {
            requestData.current_lat = currentLat;
            requestData.current_lng = currentLng;
        }

        console.log('Finding path with data:', requestData);

        const response = await fetch(`/api/${currentLocation}/find-path`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestData)
        });

        const data = await response.json();
        console.log('Path response:', data);

        if (!response.ok) {
            throw new Error(data.error || 'Failed to find path');
        }

        if (data.success && data.path && data.path.length > 0) {
            displayNavigationPath(data.path);
            showStatus(`Route found: ${Math.round(data.total_distance)}m, ~${data.estimated_time}min`, 'success');
            return data.path;
        } else {
            showStatus('No route found between selected points', 'error');
            return null;
        }

    } catch (error) {
        console.error('Error finding path:', error);
        showStatus('Failed to find route: ' + error.message, 'error');
        return null;
    }
}

function displayNavigationPath(path) {
    if (navigationPath) {
        map.removeLayer(navigationPath);
    }

    if (path.length < 2) {
        showStatus('Path too short', 'error');
        return;
    }

    const coordinates = path.map(hop => [hop.latitude, hop.longitude]);

    navigationPath = L.polyline(coordinates, {
        color: '#00ff00',
        weight: 6,
        opacity: 0.8,
        dashArray: '10, 10'
    }).addTo(map);

    for (let i = 0; i < path.length; i++) {
        const hop = path[i];
        let popupText = '';

        if (i === 0) {
            popupText = `<b>Start:</b> ${hop.name || 'Starting Point'}`;
        } else if (i === path.length - 1) {
            popupText = `<b>Destination:</b> ${hop.name}`;
        } else {
            popupText = `<b>Via:</b> ${hop.name || 'Waypoint'}`;
        }

        L.circleMarker([hop.latitude, hop.longitude], {
            radius: 8,
            fillColor: i === 0 ? '#00ff00' : (i === path.length - 1 ? '#ff0000' : '#ffff00'),
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        }).bindPopup(popupText).addTo(map);
    }

    map.fitBounds(navigationPath.getBounds().pad(0.1));
}

function showMarkerDetails(hopId) {
    const hop = hops.get(hopId);
    if (!hop || !hop.hopData) return;

    const hopData = hop.hopData;

    const modalHTML = `
        <div id="markerModal" class="marker-modal" onclick="closeMarkerModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3>${hopData.name}</h3>
                    <button class="modal-close" onclick="closeMarkerModal()">&times;</button>
                </div>
                <div class="modal-body">
                    ${hopData.image_path ?
                        `<div class="modal-image-container">
                            <img src="/storage/${hopData.image_path}" alt="${hopData.name}" class="modal-image">
                        </div>` :
                        ''
                    }
                    ${hopData.description ?
                        `<div class="modal-description">
                            <h4>Description:</h4>
                            <p>${hopData.description}</p>
                        </div>` :
                        '<p>No description available.</p>'
                    }
                    <div class="modal-coordinates">
                        <small><strong>Location:</strong> ${hopData.latitude.toFixed(6)}, ${hopData.longitude.toFixed(6)}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeMarkerModal()">Close</button>
                    <button class="btn btn-success" onclick="navigateToMarker(${hopData.id})">Navigate Here</button>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('markerModal');
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    setTimeout(() => {
        document.getElementById('markerModal').classList.add('show');
    }, 10);
}

function closeMarkerModal(event) {
    if (event && event.target.classList.contains('modal-content')) return;

    const modal = document.getElementById('markerModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

function navigateToMarker(hopId) {
    closeMarkerModal();
    const hop = hops.get(hopId);
    if (hop && hop.hopData) {
        map.setView([hop.hopData.latitude, hop.hopData.longitude], 18);
        hop.openPopup();
        showStatus(`Centered on ${hop.hopData.name}`, 'success');
    }
}

function showAllMarkerDetails() {
    const markerHops = Array.from(hops.values()).filter(hop => hop.hopData.type === 'marker');

    if (markerHops.length === 0) {
        showStatus('No markers found', 'info');
        return;
    }

    let modalHTML = `
        <div id="allMarkersModal" class="marker-modal" onclick="closeAllMarkersModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 700px;">
                <div class="modal-header">
                    <h3>All Markers (${markerHops.length})</h3>
                    <button class="modal-close" onclick="closeAllMarkersModal()">&times;</button>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <div class="markers-grid">
    `;

    markerHops.forEach(hop => {
        const data = hop.hopData;
        modalHTML += `
            <div class="marker-card">
                <div class="marker-card-header">
                    <h4>${data.name}</h4>
                    <button class="btn btn-sm" onclick="navigateToMarker(${data.id}); closeAllMarkersModal();">🎯 Go To</button>
                </div>
                ${data.image_path ?
                    `<div class="marker-card-image">
                        <img src="/storage/${data.image_path}" alt="${data.name}">
                    </div>` : ''
                }
                <div class="marker-card-content">
                    ${data.description ? `<p>${data.description}</p>` : '<p><em>No description available</em></p>'}
                    <small>📍 ${data.latitude.toFixed(6)}, ${data.longitude.toFixed(6)}</small>
                </div>
                <div class="marker-card-actions">
                    <button class="btn btn-info btn-sm" onclick="showMarkerDetails(${data.id}); closeAllMarkersModal();">👁️ Details</button>
                    <button class="btn btn-danger btn-sm" onclick="removeHop(${data.id}); closeAllMarkersModal();">🗑️ Remove</button>
                </div>
            </div>
        `;
    });

    modalHTML += `
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeAllMarkersModal()">Close</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    setTimeout(() => document.getElementById('allMarkersModal').classList.add('show'), 10);
}

function closeAllMarkersModal(event) {
    if (event && event.target.classList.contains('modal-content')) return;

    const modal = document.getElementById('allMarkersModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

function createTestData() {
    if (!confirm('This will create test markers and connections. Continue?')) {
        return;
    }

    const testMarkers = [
        { name: 'Library', lat: 51.505, lng: -0.09, desc: 'Main campus library' },
        { name: 'Cafeteria', lat: 51.506, lng: -0.091, desc: 'Student dining area' },
        { name: 'Gym', lat: 51.507, lng: -0.089, desc: 'Fitness center' },
        { name: 'Parking', lat: 51.504, lng: -0.088, desc: 'Main parking lot' }
    ];

    testMarkers.forEach(async (marker, index) => {
        try {
            const response = await fetch(`/api/${currentLocation}/hops`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    type: 'marker',
                    name: marker.name,
                    description: marker.desc,
                    latitude: marker.lat,
                    longitude: marker.lng
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    addHopToMap(data.hop);
                    console.log(`Created marker: ${marker.name}`);
                }
            }
        } catch (error) {
            console.error(`Error creating marker ${marker.name}:`, error);
        }
    });

    setTimeout(() => {
        loadMapData();
        showStatus('Test markers created. Now manually connect them with roads to test navigation.', 'success');
    }, 2000);
}

function addTestDataButton() {
    const controlPanel = document.querySelector('.control-panel');
    if (controlPanel && !document.getElementById('testDataBtn')) {
        const testSection = document.createElement('div');
        testSection.className = 'control-section';
        testSection.innerHTML = `
            <h3>🧪 Test Data</h3>
            <button id="testDataBtn" class="btn btn-warning" onclick="createTestData()">Create Test Data</button>
            <button class="btn btn-info" onclick="debugNavigation()">Debug Navigation</button>
        `;
        controlPanel.appendChild(testSection);
    }
}

function debugNavigation() {
    console.log('=== Navigation Debug Info ===');
    console.log('Current location:', currentLocation);
    console.log('Hops count:', hops.size);
    console.log('Connections count:', connections.size);
    console.log('Hops:', Array.from(hops.values()).map(h => ({ id: h.hopData.id, name: h.hopData.name, type: h.hopData.type })));
    console.log('Connections:', Array.from(connections.values()).map(c => ({ id: c.connectionData.id, from: c.connectionData.hop_from_id, to: c.connectionData.hop_to_id })));

    const markerCount = Array.from(hops.values()).filter(h => h.hopData.type === 'marker').length;
    const hopCount = Array.from(hops.values()).filter(h => h.hopData.type === 'hop').length;

    showStatus(`Debug: ${markerCount} markers, ${hopCount} hops, ${connections.size} connections`, 'info');
}

function showStatus(message, type = 'info') {
    console.log(`Status (${type}):`, message);

    let statusEl = document.getElementById('statusMessage');
    if (!statusEl) {
        statusEl = document.createElement('div');
        statusEl.id = 'statusMessage';
        statusEl.className = 'status-message';
        document.body.appendChild(statusEl);
    }

    statusEl.className = `status-message ${type}`;
    statusEl.textContent = message;
    statusEl.style.display = 'block';

    const delay = type === 'error' ? 7000 : 4000;
    setTimeout(() => {
        if (statusEl.style.display === 'block') {
            statusEl.style.display = 'none';
        }
    }, delay);
}

function showMode(text, className = '') {
    const indicator = document.getElementById('modeIndicator');
    if (!indicator) return;

    indicator.textContent = text;
    indicator.style.display = 'block';
    indicator.className = 'mode-indicator ' + className;
}

function hideMode() {
    const indicator = document.getElementById('modeIndicator');
    if (indicator) indicator.style.display = 'none';
}

function initializeAdminMap(locationCode) {
    initMap(locationCode);
}

// Show all marker details in a list
function showAllMarkerDetails() {
    const markerHops = Array.from(hops.values()).filter(hop => hop.hopData.type === 'marker');

    if (markerHops.length === 0) {
        showStatus('No markers found', 'info');
        return;
    }

    let modalHTML = `
        <div id="allMarkersModal" class="marker-modal" onclick="closeAllMarkersModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 700px;">
                <div class="modal-header">
                    <h3>All Markers (${markerHops.length})</h3>
                    <button class="modal-close" onclick="closeAllMarkersModal()">&times;</button>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <div class="markers-grid">
    `;

    markerHops.forEach(hop => {
        const data = hop.hopData;
        modalHTML += `
            <div class="marker-card">
                <div class="marker-card-header">
                    <h4>${data.name}</h4>
                    <button class="btn btn-sm" onclick="navigateToMarker(${data.id}); closeAllMarkersModal();">🎯 Go To</button>
                </div>
                ${data.image_path ?
                    `<div class="marker-card-image">
                        <img src="/storage/${data.image_path}" alt="${data.name}">
                    </div>` : ''
                }
                <div class="marker-card-content">
                    ${data.description ? `<p>${data.description}</p>` : '<p><em>No description available</em></p>'}
                    <small>📍 ${data.latitude.toFixed(6)}, ${data.longitude.toFixed(6)}</small>
                </div>
                <div class="marker-card-actions">
                    <button class="btn btn-info btn-sm" onclick="showMarkerDetails(${data.id}); closeAllMarkersModal();">👁️ Details</button>
                    <button class="btn btn-danger btn-sm" onclick="removeHop(${data.id}); closeAllMarkersModal();">🗑️ Remove</button>
                </div>
            </div>
        `;
    });

    modalHTML += `
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" onclick="closeAllMarkersModal()">Close</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    setTimeout(() => document.getElementById('allMarkersModal').classList.add('show'), 10);
}

function closeAllMarkersModal(event) {
    if (event && event.target.classList.contains('modal-content')) return;

    const modal = document.getElementById('allMarkersModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

// Make functions globally available
window.showAllMarkerDetails = showAllMarkerDetails;
window.closeAllMarkersModal = closeAllMarkersModal;

// Make functions globally available
window.showMarkerDetails = showMarkerDetails;
window.closeMarkerModal = closeMarkerModal;
window.navigateToMarker = navigateToMarker;
window.showAllMarkerDetails = showAllMarkerDetails;
window.closeAllMarkersModal = closeAllMarkersModal;
window.createTestData = createTestData;
window.debugNavigation = debugNavigation;
window.searchLocation = searchLocation;
window.startBoundarySelection = startBoundarySelection;
window.clearBoundaries = clearBoundaries;
window.startAddingMarker = startAddingMarker;
window.startAddingHop = startAddingHop;
window.clearAllHops = clearAllHops;
window.startConnectingHops = startConnectingHops;
window.finishConnection = finishConnection;
window.startOverlayPlacement = startOverlayPlacement;
window.startNavigation = startNavigation;
window.navigateFromCurrent = navigateFromCurrent;
window.removeHop = removeHop;

// // Initialize test data button when map loads
// document.addEventListener('DOMContentLoaded', function() {
//     setTimeout(addTestDataButton, 1000);
// });
