<?php

namespace App\Http\Controllers;

use App\Models\Hop;
use App\Models\RoadConnection;
use App\Models\CampusBoundary;
use App\Models\Overlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    public function getMapData()
    {
        $hops = Hop::with(['connectionsFrom.hopTo', 'connectionsTo.hopFrom'])->get();

        return response()->json([
            'hops' => $hops,
            'connections' => RoadConnection::with(['hopFrom', 'hopTo'])->get(),
            'boundary' => CampusBoundary::first(),
            'overlays' => Overlay::where('active', true)->get(),
        ]);
    }

    public function saveHop(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:marker,hop',
            'name' => 'required_if:type,marker|nullable|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'icon' => 'nullable|string',
        ]);

        $hop = Hop::create($validated);
        return response()->json($hop);
    }

    public function connectHops(Request $request)
    {
        $validated = $request->validate([
            'hop_from_id' => 'required|exists:hops,id',
            'hop_to_id' => 'required|exists:hops,id|different:hop_from_id',
            'name' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:1|max:20',
            'color' => 'nullable|string',
        ]);

        // Check if connection already exists
        $exists = RoadConnection::where(function($query) use ($validated) {
            $query->where('hop_from_id', $validated['hop_from_id'])
                  ->where('hop_to_id', $validated['hop_to_id']);
        })->orWhere(function($query) use ($validated) {
            $query->where('hop_from_id', $validated['hop_to_id'])
                  ->where('hop_to_id', $validated['hop_from_id']);
        })->exists();

        if ($exists) {
            return response()->json(['error' => 'Connection already exists'], 422);
        }

        $connection = RoadConnection::create($validated);
        $connection->distance = $connection->calculateDistance();
        $connection->save();

        $connection->load(['hopFrom', 'hopTo']);
        return response()->json($connection);
    }

    public function findPath(Request $request)
    {
        $validated = $request->validate([
            'start_id' => 'nullable|exists:hops,id',
            'end_id' => 'required|exists:hops,id',
            'current_lat' => 'nullable|numeric',
            'current_lng' => 'nullable|numeric',
        ]);

        if ($validated['start_id']) {
            $startHop = Hop::find($validated['start_id']);
        } else {
            // Find nearest hop to current position
            $startHop = $this->findNearestHop($validated['current_lat'], $validated['current_lng']);
        }

        $endHop = Hop::find($validated['end_id']);

        $path = $this->dijkstraPath($startHop, $endHop);

        return response()->json([
            'path' => $path,
            'start_hop' => $startHop,
            'end_hop' => $endHop,
        ]);
    }

    private function findNearestHop($lat, $lng)
    {
        $hops = Hop::all();
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($hops as $hop) {
            $distance = $this->calculateDistance($lat, $lng, $hop->latitude, $hop->longitude);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $hop;
            }
        }

        return $nearest;
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // meters
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon/2) * sin($deltaLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    private function dijkstraPath($start, $end)
    {
        $distances = [];
        $previous = [];
        $unvisited = [];

        $hops = Hop::all();

        foreach ($hops as $hop) {
            $distances[$hop->id] = PHP_FLOAT_MAX;
            $previous[$hop->id] = null;
            $unvisited[$hop->id] = true;
        }

        $distances[$start->id] = 0;

        while (count($unvisited) > 0) {
            // Find unvisited node with minimum distance
            $current = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($unvisited as $id => $value) {
                if ($distances[$id] < $minDistance) {
                    $minDistance = $distances[$id];
                    $current = $id;
                }
            }

            if ($current === null || $current == $end->id) {
                break;
            }

            unset($unvisited[$current]);

            $currentHop = Hop::find($current);
            $connections = RoadConnection::where('hop_from_id', $current)
                                        ->orWhere('hop_to_id', $current)
                                        ->get();

            foreach ($connections as $connection) {
                $neighborId = $connection->hop_from_id == $current
                    ? $connection->hop_to_id
                    : $connection->hop_from_id;

                if (!isset($unvisited[$neighborId])) {
                    continue;
                }

                $alt = $distances[$current] + $connection->distance;

                if ($alt < $distances[$neighborId]) {
                    $distances[$neighborId] = $alt;
                    $previous[$neighborId] = $current;
                }
            }
        }

        // Reconstruct path
        $path = [];
        $current = $end->id;

        while ($current !== null) {
            array_unshift($path, Hop::find($current));
            $current = $previous[$current];
        }

        return $path;
    }

    public function saveBoundary(Request $request)
    {
        $validated = $request->validate([
            'north' => 'required|numeric',
            'south' => 'required|numeric',
            'east' => 'required|numeric',
            'west' => 'required|numeric',
        ]);

        CampusBoundary::truncate();
        $boundary = CampusBoundary::create($validated);
        return response()->json($boundary);
    }

    public function uploadOverlay(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'corners' => 'required|json',
            'opacity' => 'nullable|numeric|min:0|max:1',
        ]);

        $path = $request->file('image')->store('overlays', 'public');

        $overlay = Overlay::create([
            'name' => $validated['name'],
            'image_path' => $path,
            'corners' => json_decode($validated['corners'], true),
            'opacity' => $validated['opacity'] ?? 0.7,
        ]);

        return response()->json($overlay);
    }

    public function deleteHop(Hop $hop)
    {
        $hop->delete();
        return response()->json(['success' => true]);
    }

    public function deleteConnection(RoadConnection $connection)
    {
        $connection->delete();
        return response()->json(['success' => true]);
    }

    public function getCurrentLocation()
    {
        // This would normally get the user's actual location
        // For demo purposes, return a default location
        return response()->json([
            'latitude' => 51.505,
            'longitude' => -0.09
        ]);
    }
}
