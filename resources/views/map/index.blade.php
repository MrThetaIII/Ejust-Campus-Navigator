<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Campus Navigator</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Custom CSS -->
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        #map {
            height: 100vh;
            width: 100%;
        }

        .control-panel {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 1000;
            max-width: 350px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .control-section {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        .control-section:last-child {
            border-bottom: none;
        }

        .control-section h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .input-group {
            margin-bottom: 10px;
        }

        .input-group label {
            display: block;
            margin-bottom: 3px;
            font-size: 14px;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .search-box {
            display: flex;
            margin-bottom: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px 0 0 3px;
        }

        .search-box button {
            background: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 0 3px 3px 0;
            cursor: pointer;
        }

        .info-display {
            background: #f8f9fa;
            padding: 5px;
            border-radius: 3px;
            margin-top: 10px;
            font-size: 12px;
        }

        .hop-list {
            max-height: 150px;
            overflow-y: auto;
            font-size: 14px;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            margin-bottom: 3px;
            background: #f8f9fa;
            border-radius: 3px;
        }

        .color-picker {
            width: 50px;
            height: 30px;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
        }

        .status-message {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: none;
            z-index: 2000;
        }

        .mode-indicator {
            background: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 3px;
            margin-bottom: 10px;
            text-align: center;
            font-weight: bold;
        }

        .connection-mode {
            background: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div id="map"></div>

    <div class="control-panel">
        <!-- Mode Indicator -->
        <div id="modeIndicator" class="mode-indicator" style="display:none;"></div>

        <!-- Search Section -->
        <div class="control-section">
            <h3>Search Location</h3>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search for a place...">
                <button onclick="searchLocation()">Search</button>
            </div>
        </div>

        <!-- Boundaries Section -->
        <div class="control-section">
            <h3>Map Boundaries</h3>
            <button class="btn" onclick="startBoundarySelection()">Define Boundaries</button>
            <button class="btn btn-danger" onclick="clearBoundaries()">Clear</button>
            <div id="boundaryInfo" class="info-display" style="display:none;"></div>
        </div>

        <!-- Hops & Markers Section -->
        <div class="control-section">
            <h3>Hops & Markers</h3>
            <button class="btn" onclick="startAddingMarker()">Add Marker</button>
            <button class="btn btn-warning" onclick="startAddingHop()">Add Hop</button>
            <button class="btn btn-danger" onclick="clearAllHops()">Clear All</button>
            <div class="hop-list" id="hopList"></div>
        </div>

        <!-- Roads Section -->
        <div class="control-section">
            <h3>Road Connections</h3>
            <button class="btn" onclick="startConnectingHops()">Connect Hops</button>
            <button class="btn btn-success" id="finishConnectionBtn" style="display:none;" onclick="finishConnection()">Finish Connection</button>
            <div class="input-group">
                <label>Road Width:</label>
                <input type="range" id="roadWidth" min="2" max="20" value="5">
                <span id="roadWidthDisplay">5</span>
            </div>
            <div class="input-group">
                <label>Road Color:</label>
                <input type="color" id="roadColor" value="#0066cc" class="color-picker">
            </div>
        </div>

        <!-- Overlay Section -->
        <div class="control-section">
            <h3>Campus Overlay</h3>
            <input type="file" id="overlayImage" accept="image/*" style="margin-bottom: 10px;">
            <button class="btn" onclick="startOverlayPlacement()">Place Overlay</button>
            <div class="input-group">
                <label>Opacity:</label>
                <input type="range" id="overlayOpacity" min="0" max="100" value="70">
                <span id="opacityDisplay">70%</span>
            </div>
        </div>

        <!-- Coordinates Display -->
        <div class="control-section">
            <h3>Coordinates</h3>
            <div id="coordinatesDisplay" class="info-display">
                Hover over the map to see coordinates
            </div>
        </div>

        <!-- Navigation Section -->
        <div class="control-section">
            <h3>Navigation</h3>
            <button class="btn" onclick="startNavigation()">Navigate to Marker</button>
            <button class="btn btn-success" onclick="navigateFromCurrent()">From Current Location</button>
            <div id="navigationInfo" class="info-display" style="display:none;">
                Select destination marker
            </div>
        </div>
    </div>

    <div class="status-message" id="statusMessage"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Main JavaScript -->
    <script>
        // Global variables
        let map;
        let hops = new Map(); // Store hops by ID
        let connections = new Map(); // Store connections
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

        // Initialize map
        function initMap() {
            // Default center (you can change this to your campus coordinates)
            map = L.map('map').setView([51.505, -0.09], 16);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add mouse move handler for coordinates
            map.on('mousemove', function(e) {
                document.getElementById('coordinatesDisplay').innerHTML =
                    `Lat: ${e.latlng.lat.toFixed(6)}<br>Lng: ${e.latlng.lng.toFixed(6)}`;
            });

            // Load existing data
            loadMapData();

            // Setup event listeners
            setupEventListeners();
        }

        // Setup event listeners
        function setupEventListeners() {
            // Road width slider
            document.getElementById('roadWidth').addEventListener('input', function(e) {
                document.getElementById('roadWidthDisplay').textContent = e.target.value;
            });

            // Overlay opacity slider
            document.getElementById('overlayOpacity').addEventListener('input', function(e) {
                document.getElementById('opacityDisplay').textContent = e.target.value + '%';
                if (overlayImage) {
                    overlayImage.setOpacity(e.target.value / 100);
                }
            });
        }

        // Load existing map data
        async function loadMapData() {
            try {
                const response = await fetch('/api/map-data');
                const data = await response.json();

                // Load boundaries
                if (data.boundary) {
                    setBoundaries(data.boundary);
                }

                // Load hops (including markers)
                data.hops.forEach(hopData => {
                    addHopToMap(hopData);
                });

                // Load connections
                data.connections.forEach(connectionData => {
                    addConnectionToMap(connectionData);
                });

                // Load overlays
                data.overlays.forEach(overlayData => {
                    addOverlayToMap(overlayData);
                });

                updateHopList();
            } catch (error) {
                console.error('Error loading map data:', error);
            }
        }

        // Search location
        async function searchLocation() {
            const query = document.getElementById('searchInput').value;
            if (!query) return;

            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.length > 0) {
                    const result = data[0];
                    const lat = parseFloat(result.lat);
                    const lng = parseFloat(result.lon);

                    // Check if within boundaries
                    if (boundaries && !isWithinBoundaries(lat, lng)) {
                        showStatus('Location is outside campus boundaries', 'error');
                        return;
                    }

                    map.setView([lat, lng], 18);

                    // Add temporary marker
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

        // Boundary functions
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
                const response = await fetch('/api/boundary', {
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

            // Restrict map to boundaries
            map.setMaxBounds(bounds);
            map.fitBounds(bounds);

            document.getElementById('boundaryInfo').style.display = 'block';
            document.getElementById('boundaryInfo').innerHTML =
                `N: ${boundaries.north.toFixed(6)}<br>
                 S: ${boundaries.south.toFixed(6)}<br>
                 E: ${boundaries.east.toFixed(6)}<br>
                 W: ${boundaries.west.toFixed(6)}`;
        }

        function clearBoundaries() {
            if (boundaryRectangle) {
                map.removeLayer(boundaryRectangle);
                boundaryRectangle = null;
            }
            boundaries = null;
            map.setMaxBounds(null);
            document.getElementById('boundaryInfo').style.display = 'none';
            showStatus('Boundaries cleared', 'info');
        }

        function isWithinBoundaries(lat, lng) {
            if (!boundaries) return true;
            return lat >= boundaries.south && lat <= boundaries.north &&
                   lng >= boundaries.west && lng <= boundaries.east;
        }

        // Hop and Marker functions
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

                const name = prompt('Enter marker name:');
                if (name) {
                    const description = prompt('Enter description (optional):');
                    saveHop({
                        type: 'marker',
                        name: name,
                        description: description,
                        latitude: e.latlng.lat,
                        longitude: e.latlng.lng
                    });
                }
                currentMode = 'view';
                hideMode();
            });
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
                const response = await fetch('/api/hops', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(hopData)
                });

                if (response.ok) {
                    const savedHop = await response.json();
                    addHopToMap(savedHop);
                    updateHopList();
                    showStatus(`${hopData.type === 'marker' ? 'Marker' : 'Hop'} added successfully`, 'success');
                }
            } catch (error) {
                console.error('Error saving hop:', error);
                showStatus('Failed to save', 'error');
            }
        }

        function addHopToMap(hopData) {
            let hopElement;

            if (hopData.type === 'marker') {
                // Create marker with label
                hopElement = L.marker([hopData.latitude, hopData.longitude])
                    .bindPopup(`<b>${hopData.name}</b><br>${hopData.description || ''}`);
            } else {
                // Create simple dot for hop
                hopElement = L.circleMarker([hopData.latitude, hopData.longitude], {
                    radius: 6,
                    fillColor: '#3388ff',
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).bindPopup(`<b>${hopData.name}</b>`);
            }

            hopElement.addTo(map);
            hopElement.hopData = hopData;

            // Add click handler for navigation and connections
            hopElement.on('click', function() {
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
            list.innerHTML = '';

            hops.forEach((hop, id) => {
                const item = document.createElement('div');
                item.className = 'list-item';
                const type = hop.hopData.type === 'marker' ? '📍' : '•';
                item.innerHTML = `
                    <span>${type} ${hop.hopData.name}</span>
                    <button class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;"
                            onclick="removeHop(${id})">Remove</button>
                `;
                list.appendChild(item);
            });
        }

        async function removeHop(hopId) {
            try {
                const response = await fetch(`/api/hops/${hopId}`, {
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

                        // Remove associated connections
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

        // Connection functions
        function startConnectingHops() {
            connectionMode = true;
            firstHop = null;
            showMode('Select First Hop', 'connection-mode');
            document.getElementById('finishConnectionBtn').style.display = 'inline-block';
        }

        function handleConnectionClick(hopData) {
            if (!firstHop) {
                firstHop = hopData;
                showMode('Select Second Hop', 'connection-mode');

                // Show temporary line on mouse move
                map.on('mousemove', drawTemporaryLine);
            } else {
                // Create connection
                createConnection(firstHop, hopData);
                finishConnection();
            }
        }

        function drawTemporaryLine(e) {
            if (!firstHop) return;

            if (temporaryLine) {
                map.removeLayer(temporaryLine);
            }

            const firstHopElement = hops.get(firstHop.id);
            temporaryLine = L.polyline([
                [firstHop.latitude, firstHop.longitude],
                [e.latlng.lat, e.latlng.lng]
            ], {
                color: document.getElementById('roadColor').value,
                weight: parseInt(document.getElementById('roadWidth').value),
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
                width: parseInt(document.getElementById('roadWidth').value),
                color: document.getElementById('roadColor').value
            };

            try {
                const response = await fetch('/api/connections', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(connectionData)
                });

                if (response.ok) {
                    const savedConnection = await response.json();
                    addConnectionToMap(savedConnection);
                    showStatus('Connection created', 'success');
                } else {
                    const error = await response.json();
                    showStatus(error.error || 'Failed to create connection', 'error');
                }
            } catch (error) {
                console.error('Error creating connection:', error);
                showStatus('Failed to create connection', 'error');
            }
        }

        function addConnectionToMap(connectionData) {
            const fromHop = connectionData.hop_from || hops.get(connectionData.hop_from_id)?.hopData;
            const toHop = connectionData.hop_to || hops.get(connectionData.hop_to_id)?.hopData;

            if (!fromHop || !toHop) return;

            const connection = L.polyline([
                [fromHop.latitude, fromHop.longitude],
                [toHop.latitude, toHop.longitude]
            ], {
                color: connectionData.color,
                weight: connectionData.width,
                opacity: 0.8
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
            document.getElementById('finishConnectionBtn').style.display = 'none';
            hideMode();
        }

        // Overlay functions
        function startOverlayPlacement() {
            const fileInput = document.getElementById('overlayImage');
            if (!fileInput.files || !fileInput.files[0]) {
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

            // Add temporary marker
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
                // Clear temporary markers
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
            formData.append('opacity', document.getElementById('overlayOpacity').value / 100);

            try {
                const response = await fetch('/api/overlay', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: formData
                });

                if (response.ok) {
                    const overlayData = await response.json();

                    // Clear temporary markers
                    map.eachLayer(layer => {
                        if (layer instanceof L.CircleMarker && layer.options.color === '#ff0000') {
                            map.removeLayer(layer);
                        }
                    });

                    addOverlayToMap(overlayData);
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
        }

        function addOverlayToMap(overlayData) {
            const imageUrl = `/storage/${overlayData.image_path}`;
            const corners = overlayData.corners;

            // Create bounds from corners
            // Assuming corners are in order: NW, NE, SE, SW
            const bounds = L.latLngBounds([
                [corners[2][0], corners[3][1]], // SW corner
                [corners[0][0], corners[1][1]]  // NE corner
            ]);

            overlayImage = L.imageOverlay(imageUrl, bounds, {
                opacity: overlayData.opacity,
                interactive: true
            }).addTo(map);

            overlays.push(overlayImage);
        }

        // Navigation functions
        function startNavigation() {
            navigationMode = true;
            navigationFromCurrent = false;
            document.getElementById('navigationInfo').style.display = 'block';
            document.getElementById('navigationInfo').innerHTML = 'Click on destination marker';
            showStatus('Click on a marker to navigate to it', 'info');
        }

        function navigateFromCurrent() {
            // Get current location (for demo, we'll use map center or GPS if available)
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
                        document.getElementById('navigationInfo').style.display = 'block';
                        document.getElementById('navigationInfo').innerHTML = 'Click on destination marker';
                        showStatus('Click on a marker to navigate from your location', 'info');
                    },
                    error => {
                        // Fallback to map center
                        const center = map.getCenter();
                        showCurrentLocation(center.lat, center.lng);
                        navigationMode = true;
                        navigationFromCurrent = true;
                        document.getElementById('navigationInfo').style.display = 'block';
                        document.getElementById('navigationInfo').innerHTML = 'Click on destination marker';
                        showStatus('Using map center as current location. Click destination marker.', 'info');
                    }
                );
            } else {
                // Fallback to map center
                const center = map.getCenter();
                showCurrentLocation(center.lat, center.lng);
                navigationMode = true;
                navigationFromCurrent = true;
                document.getElementById('navigationInfo').style.display = 'block';
                document.getElementById('navigationInfo').innerHTML = 'Click on destination marker';
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
                // Find a start marker
                const startHop = prompt('Enter the name of your starting marker:');
                if (!startHop) {
                    navigationMode = false;
                    document.getElementById('navigationInfo').style.display = 'none';
                    return;
                }

                // Find the hop by name
                let found = false;
                hops.forEach(hop => {
                    if (hop.hopData.name.toLowerCase() === startHop.toLowerCase()) {
                        startId = hop.hopData.id;
                        found = true;
                    }
                });

                if (!found) {
                    showStatus('Starting marker not found', 'error');
                    return;
                }
            }

            // Find path
            try {
                const response = await fetch('/api/find-path', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        start_id: startId,
                        end_id: destinationHop.id,
                        current_lat: currentLat,
                        current_lng: currentLng
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    displayNavigationPath(data.path);
                    showStatus(`Route found to ${destinationHop.name}`, 'success');
                } else {
                    showStatus('No path found', 'error');
                }
            } catch (error) {
                console.error('Error finding path:', error);
                showStatus('Failed to find path', 'error');
            }

            navigationMode = false;
            navigationFromCurrent = false;
            document.getElementById('navigationInfo').style.display = 'none';
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

            // Add direction markers
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

            // Fit map to show the entire route
            map.fitBounds(navigationPath.getBounds().pad(0.1));
        }

        // Utility functions
        function showStatus(message, type = 'info') {
            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = message;
            statusEl.style.display = 'block';

            switch(type) {
                case 'success':
                    statusEl.style.background = '#28a745';
                    break;
                case 'error':
                    statusEl.style.background = '#dc3545';
                    break;
                default:
                    statusEl.style.background = '#333';
            }

            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 3000);
        }

        function showMode(text, className = '') {
            const indicator = document.getElementById('modeIndicator');
            indicator.textContent = text;
            indicator.style.display = 'block';
            indicator.className = 'mode-indicator ' + className;
        }

        function hideMode() {
            document.getElementById('modeIndicator').style.display = 'none';
        }

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>
