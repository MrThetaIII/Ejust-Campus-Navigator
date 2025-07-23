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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        #map {
            height: 100vh;
            width: 100%;
        }

        .navigation-panel {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            width: 300px;
        }

        .navigation-panel h2 {
            margin: 0 0 15px 0;
            font-size: 20px;
            color: #333;
        }

        .nav-section {
            margin-bottom: 20px;
        }

        .nav-button {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .nav-button:hover {
            background: #0056b3;
        }

        .nav-button.current-location {
            background: #28a745;
        }

        .nav-button.current-location:hover {
            background: #218838;
        }

        .nav-button.clear {
            background: #6c757d;
        }

        .nav-button.clear:hover {
            background: #5a6268;
        }

        .navigation-mode {
            background: #e9ecef;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }

        .navigation-mode h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #495057;
        }

        .navigation-mode p {
            margin: 0;
            font-size: 14px;
            color: #6c757d;
        }

        .marker-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }

        .marker-info strong {
            color: #333;
        }

        .coordinates-display {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 1000;
        }

        .legend {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .legend h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .legend-icon {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border-radius: 50%;
        }

        .legend-marker {
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMwMDdiZmYiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMjEgMTBjMCA3LTkgMTMtOSAxM3MtOS02LTktMTNhOSA5IDAgMCAxIDE4IDB6Ii8+PGNpcmNsZSBjeD0iMTIiIGN5PSIxMCIgcj0iMyIvPjwvc3ZnPg==') center/contain no-repeat;
        }

        .legend-road {
            height: 4px;
            background: #0066cc;
        }

        .legend-path {
            height: 4px;
            background: #00ff00;
            border: 1px dashed #00cc00;
        }

        .popup-content {
            font-size: 14px;
        }

        .popup-content h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .popup-content p {
            margin: 0;
            color: #666;
        }

        @media (max-width: 600px) {
            .navigation-panel {
                width: calc(100% - 40px);
                left: 20px;
                right: 20px;
            }

            .legend {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div id="map"></div>

    <div class="navigation-panel">
        <h2>Campus Navigation</h2>

        <div id="navigationMode" class="navigation-mode" style="display: none;">
            <h3 id="modeTitle">Navigation Mode</h3>
            <p id="modeDescription">Select your destination</p>
        </div>

        <div class="nav-section">
            <button class="nav-button" onclick="startMarkerToMarker()">
                📍 Navigate Between Markers
            </button>
            <button class="nav-button current-location" onclick="startFromCurrentLocation()">
                📍 Navigate from My Location
            </button>
            <button class="nav-button clear" onclick="clearNavigation()">
                ✖ Clear Navigation
            </button>
        </div>

        <div id="selectedMarkers" class="marker-info" style="display: none;">
            <div id="startMarkerInfo"></div>
            <div id="endMarkerInfo"></div>
        </div>
    </div>

    <div class="coordinates-display" id="coordinatesDisplay">
        Lat: 0.000000, Lng: 0.000000
    </div>

    <div class="legend">
        <h4>Legend</h4>
        <div class="legend-item">
            <div class="legend-icon legend-marker"></div>
            <span>Location Marker</span>
        </div>
        <div class="legend-item">
            <div class="legend-icon legend-road"></div>
            <span>Walking Path</span>
        </div>
        <div class="legend-item">
            <div class="legend-icon legend-path"></div>
            <span>Navigation Route</span>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Main JavaScript -->
    <script>
        // Global variables
        let map;
        let hops = new Map();
        let connections = new Map();
        let overlays = [];
        let boundaries = null;
        let boundaryRectangle = null;
        let navigationMode = false;
        let navigationFromCurrent = false;
        let navigationPath = null;
        let currentLocationMarker = null;
        let startMarker = null;
        let endMarker = null;
        let pathMarkers = [];

        // Initialize map
        function initMap() {
            // Default center
            map = L.map('map').setView([51.505, -0.09], 16);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add mouse move handler for coordinates
            map.on('mousemove', function(e) {
                document.getElementById('coordinatesDisplay').innerHTML =
                    `Lat: ${e.latlng.lat.toFixed(6)}, Lng: ${e.latlng.lng.toFixed(6)}`;
            });

            // Load map data
            loadMapData();
        }

        // Load map data
        async function loadMapData() {
            try {
                const response = await fetch('/user/api/map-data');
                const data = await response.json();

                // Load boundaries
                if (data.boundary) {
                    setBoundaries(data.boundary);
                }

                // Load hops (only markers for user view)
                data.hops.forEach(hopData => {
                    if (hopData.type === 'marker') {
                        addMarkerToMap(hopData);
                    }
                });

                // Load connections (roads)
                data.connections.forEach(connectionData => {
                    addConnectionToMap(connectionData);
                });

                // Load overlays
                data.overlays.forEach(overlayData => {
                    addOverlayToMap(overlayData);
                });

            } catch (error) {
                console.error('Error loading map data:', error);
            }
        }

        // Set boundaries
        function setBoundaries(boundaryData) {
            boundaries = boundaryData;

            const bounds = [[boundaries.south, boundaries.west], [boundaries.north, boundaries.east]];
            boundaryRectangle = L.rectangle(bounds, {
                color: '#ff0000',
                weight: 2,
                opacity: 0.5,
                fillOpacity: 0.1,
                dashArray: '5, 5',
                interactive: false
            }).addTo(map);

            // Restrict map to boundaries
            map.setMaxBounds(bounds);
            map.fitBounds(bounds);
        }

        // Add marker to map
        function addMarkerToMap(markerData) {
            const marker = L.marker([markerData.latitude, markerData.longitude], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background: #007bff; color: white; padding: 5px 10px; border-radius: 20px; white-space: nowrap; font-size: 12px; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">${markerData.name}</div>`,
                    iconSize: [null, null],
                    iconAnchor: [50, 20]
                })
            });

            marker.bindPopup(`
                <div class="popup-content">
                    <h4>${markerData.name}</h4>
                    ${markerData.description ? `<p>${markerData.description}</p>` : ''}
                </div>
            `);

            marker.addTo(map);
            marker.markerData = markerData;

            // Add click handler for navigation
            marker.on('click', function() {
                if (navigationMode) {
                    handleMarkerClick(markerData);
                }
            });

            hops.set(markerData.id, marker);
        }

        // Add connection to map
        function addConnectionToMap(connectionData) {
            const fromHop = connectionData.hop_from;
            const toHop = connectionData.hop_to;

            if (!fromHop || !toHop) return;

            const connection = L.polyline([
                [fromHop.latitude, fromHop.longitude],
                [toHop.latitude, toHop.longitude]
            ], {
                color: connectionData.color || '#0066cc',
                weight: connectionData.width || 5,
                opacity: 0.8,
                interactive: false
            }).addTo(map);

            connections.set(connectionData.id, connection);
        }

        // Add overlay to map
        function addOverlayToMap(overlayData) {
            const imageUrl = `/storage/${overlayData.image_path}`;
            const corners = overlayData.corners;

            // Create bounds from corners
            const bounds = L.latLngBounds([
                [corners[2][0], corners[3][1]], // SW corner
                [corners[0][0], corners[1][1]]  // NE corner
            ]);

            const overlay = L.imageOverlay(imageUrl, bounds, {
                opacity: overlayData.opacity,
                interactive: false
            }).addTo(map);

            overlays.push(overlay);
        }

        // Navigation functions
        function startMarkerToMarker() {
            clearNavigation();
            navigationMode = true;
            navigationFromCurrent = false;
            startMarker = null;
            endMarker = null;

            showNavigationMode('Select Start Location', 'Click on a marker to set your starting point');
            updateSelectedMarkers();
        }

        function startFromCurrentLocation() {
            clearNavigation();

            if (navigator.geolocation) {
                showNavigationMode('Getting Location...', 'Please wait while we find your location');

                navigator.geolocation.getCurrentPosition(
                    position => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        // Check if within boundaries
                        if (boundaries && !isWithinBoundaries(lat, lng)) {
                            alert('Your location is outside the campus boundaries');
                            hideNavigationMode();
                            return;
                        }

                        showCurrentLocation(lat, lng);
                        navigationMode = true;
                        navigationFromCurrent = true;
                        showNavigationMode('Select Destination', 'Click on a marker to navigate there');
                    },
                    error => {
                        alert('Unable to get your location. Please check your location settings.');
                        hideNavigationMode();
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 5000,
                        maximumAge: 0
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }

        function isWithinBoundaries(lat, lng) {
            if (!boundaries) return true;
            return lat >= boundaries.south && lat <= boundaries.north &&
                   lng >= boundaries.west && lng <= boundaries.east;
        }

        function showCurrentLocation(lat, lng) {
            if (currentLocationMarker) {
                map.removeLayer(currentLocationMarker);
            }

            currentLocationMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'current-location-marker',
                    html: `
                        <div style="position: relative;">
                            <div style="width: 20px; height: 20px; background: #ff0000; border: 3px solid white; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>
                            <div style="position: absolute; top: -5px; left: -5px; width: 30px; height: 30px; background: rgba(255,0,0,0.2); border-radius: 50%; animation: pulse 2s infinite;"></div>
                        </div>
                    `,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(map);

            currentLocationMarker.bindPopup('<b>Your Location</b>').openPopup();
            map.setView([lat, lng], 18);
        }

        function handleMarkerClick(markerData) {
            if (!navigationMode) return;

            if (navigationFromCurrent) {
                // Navigate from current location to this marker
                endMarker = markerData;
                updateSelectedMarkers();
                findAndDisplayPath();
            } else {
                // Marker to marker navigation
                if (!startMarker) {
                    startMarker = markerData;
                    showNavigationMode('Select Destination', 'Click on another marker to navigate there');
                    updateSelectedMarkers();
                } else if (markerData.id !== startMarker.id) {
                    endMarker = markerData;
                    updateSelectedMarkers();
                    findAndDisplayPath();
                }
            }
        }

        async function findAndDisplayPath() {
            if (!endMarker) return;

            showNavigationMode('Finding Route...', 'Please wait');

            let requestData = {
                end_id: endMarker.id
            };

            if (navigationFromCurrent && currentLocationMarker) {
                const pos = currentLocationMarker.getLatLng();
                requestData.current_lat = pos.lat;
                requestData.current_lng = pos.lng;
            } else if (startMarker) {
                requestData.start_id = startMarker.id;
            }

            try {
                const response = await fetch('/user/api/find-path', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(requestData)
                });

                if (response.ok) {
                    const data = await response.json();
                    displayNavigationPath(data.path);
                    hideNavigationMode();
                } else {
                    alert('No route found to the destination');
                    clearNavigation();
                }
            } catch (error) {
                console.error('Error finding path:', error);
                alert('Failed to find route');
                clearNavigation();
            }
        }

        function displayNavigationPath(path) {
            // Clear previous path
            clearPath();

            if (path.length < 2) {
                alert('Path too short');
                return;
            }

            // Draw the path
            const coordinates = path.map(hop => [hop.latitude, hop.longitude]);

            navigationPath = L.polyline(coordinates, {
                color: '#00ff00',
                weight: 6,
                opacity: 0.8,
                dashArray: '10, 10'
            }).addTo(map);

            // Add waypoint markers
            path.forEach((hop, index) => {
                if (hop.type === 'hop' || (index > 0 && index < path.length - 1)) {
                    const marker = L.circleMarker([hop.latitude, hop.longitude], {
                        radius: 6,
                        fillColor: '#ffff00',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map);

                    pathMarkers.push(marker);
                }
            });

            // Fit map to show the entire route
            map.fitBounds(navigationPath.getBounds().pad(0.1));

            // Show navigation info
            navigationMode = false;
        }

        function clearNavigation() {
            clearPath();

            if (currentLocationMarker) {
                map.removeLayer(currentLocationMarker);
                currentLocationMarker = null;
            }

            navigationMode = false;
            navigationFromCurrent = false;
            startMarker = null;
            endMarker = null;

            hideNavigationMode();
            updateSelectedMarkers();
        }

        function clearPath() {
            if (navigationPath) {
                map.removeLayer(navigationPath);
                navigationPath = null;
            }

            pathMarkers.forEach(marker => map.removeLayer(marker));
            pathMarkers = [];
        }

        function showNavigationMode(title, description) {
            document.getElementById('navigationMode').style.display = 'block';
            document.getElementById('modeTitle').textContent = title;
            document.getElementById('modeDescription').textContent = description;
        }

        function hideNavigationMode() {
            document.getElementById('navigationMode').style.display = 'none';
        }

        function updateSelectedMarkers() {
            const container = document.getElementById('selectedMarkers');
            const startInfo = document.getElementById('startMarkerInfo');
            const endInfo = document.getElementById('endMarkerInfo');

            if (navigationFromCurrent || startMarker || endMarker) {
                container.style.display = 'block';

                if (navigationFromCurrent) {
                    startInfo.innerHTML = '<strong>Start:</strong> Current Location';
                } else if (startMarker) {
                    startInfo.innerHTML = `<strong>Start:</strong> ${startMarker.name}`;
                } else {
                    startInfo.innerHTML = '';
                }

                if (endMarker) {
                    endInfo.innerHTML = `<strong>Destination:</strong> ${endMarker.name}`;
                } else {
                    endInfo.innerHTML = '';
                }
            } else {
                container.style.display = 'none';
            }
        }

        // Add pulse animation style
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 0.8;
                }
                50% {
                    transform: scale(1.5);
                    opacity: 0.4;
                }
                100% {
                    transform: scale(1);
                    opacity: 0.8;
                }
            }
        `;
        document.head.appendChild(style);

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>
