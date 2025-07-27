<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'E-Just Campus Navigator')</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Custom CSS -->
    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="/images/logo.png" alt="E-Just Logo" class="logo">
                <span class="site-name">E-Just Campus Navigator</span>
            </div>

            <div class="nav-menu">
                <div class="nav-item dropdown">
                    <span class="dropdown-toggle">📍 {{ $location->name ?? 'Select Location' }}</span>
                    <div class="dropdown-content">
                        @foreach($locations ?? [] as $loc)
                            <a href="{{ request()->is('*/admin/*') ? '/map/'.$loc->code.'/admin' : '/map/'.$loc->code }}">
                                {{ $loc->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="nav-item dropdown">
                    <span class="dropdown-toggle">⚙️ Mode</span>
                    <div class="dropdown-content">
                        <a href="/map/{{ $location->code ?? 'main-campus' }}">👤 User View</a>
                        <a href="/map/{{ $location->code ?? 'main-campus' }}/admin">🔧 Admin View</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h4>E-Just Campus Navigator</h4>
                <p>Navigate your campus with ease</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/map/main-campus">Main Campus</a></li>
                    <li><a href="/map/northern-dorms">Northern Dorms</a></li>
                    <li><a href="/map/southern-dorms">Southern Dorms</a></li>
                    <li><a href="/map/western-dorms">Western Dorms</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <p>&copy; {{ date('Y') }} Egypt-Japan University of Science and Technology</p>
            </div>
        </div>
    </footer>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Custom JS -->
    @stack('scripts')
</body>
</html>
