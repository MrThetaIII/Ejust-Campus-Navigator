@extends('layouts.main')

@section('title', 'Campus Navigator - Admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-map.css') }}">
@endpush

@section('content')
    <div id="map"></div>

    <div class="control-panel">
        <!-- Mode Indicator -->
        <div id="modeIndicator" class="mode-indicator" style="display:none;"></div>

        <!-- Search Section -->
        <div class="control-section">
            <h3>🔍 Search Location</h3>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search for a place...">
                <button onclick="searchLocation()">Search</button>
            </div>
        </div>

        <!-- Boundaries Section -->
        <div class="control-section">
            <h3>🗺️ Map Boundaries</h3>
            <button class="btn" onclick="startBoundarySelection()">Define Boundaries</button>
            <button class="btn btn-danger" onclick="clearBoundaries()">Clear</button>
            <div id="boundaryInfo" class="info-display" style="display:none;"></div>
        </div>

        <!-- Hops & Markers Section -->
        <div class="control-section">
            <h3>📍 Hops & Markers</h3>
            <div class="input-group">
                <label>Marker Image (optional):</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="markerImage" class="file-upload-input" accept="image/*">
                    <label for="markerImage" class="file-upload-label">Choose Image...</label>
                </div>
                <div id="markerPreview" class="image-preview" style="display:none;"></div>
            </div>
            <button class="btn" onclick="startAddingMarker()">📍 Add Marker</button>
            <button class="btn btn-warning" onclick="startAddingHop()">⚪ Add Hop</button>
            <button class="btn btn-info" onclick="showAllMarkerDetails()">👁️ View All Details</button>
            <button class="btn btn-danger" onclick="clearAllHops()">🗑️ Clear All</button>
            <div class="hop-list" id="hopList"></div>
        </div>

        <!-- Roads Section -->
        <div class="control-section">
            <h3>🛣️ Road Connections</h3>
            <button class="btn" onclick="startConnectingHops()">Connect Hops</button>
            <button class="btn btn-success" id="finishConnectionBtn" style="display:none;" onclick="finishConnection()">Finish Connection</button>
            <div class="input-group">
                <label>Road Width:</label>
                <input type="range" id="roadWidth" min="2" max="20" value="5">
                <span id="roadWidthDisplay" class="range-display">5</span>
            </div>
            <div class="input-group">
                <label>Road Color:</label>
                <input type="color" id="roadColor" value="#0066cc" class="color-picker">
            </div>
        </div>

        <!-- Overlay Section -->
        <div class="control-section">
            <h3>🖼️ Campus Overlay</h3>
            <div class="input-group">
                <label>Overlay Image:</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="overlayImage" class="file-upload-input" accept="image/*">
                    <label for="overlayImage" class="file-upload-label">Choose Image...</label>
                </div>
                <div id="overlayPreview" class="image-preview" style="display:none;"></div>
            </div>
            <button class="btn" onclick="startOverlayPlacement()">Place Overlay</button>
            <div class="input-group">
                <label>Opacity:</label>
                <input type="range" id="overlayOpacity" min="0" max="100" value="70">
                <span id="opacityDisplay" class="range-display">70%</span>
            </div>
        </div>

        <!-- Coordinates Display -->
        <div class="control-section">
            <h3>📐 Coordinates</h3>
            <div id="coordinatesDisplay" class="info-display">
                Hover over the map to see coordinates
            </div>
        </div>

        <!-- Navigation Section -->
        <div class="control-section">
            <h3>🧭 Navigation Test</h3>
            <button class="btn" onclick="startNavigation()">Navigate to Marker</button>
            <button class="btn btn-success" onclick="navigateFromCurrent()">From Current Location</button>
            <div id="navigationInfo" class="info-display" style="display:none;">
                Select destination marker
            </div>
        </div>
    </div>

    <div class="status-message" id="statusMessage"></div>
    <div class="coordinates-display" id="coordinatesDisplay">
        Hover over the map to see coordinates
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-map.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeAdminMap('{{ $location->code }}');
        });
    </script>
@endpush
