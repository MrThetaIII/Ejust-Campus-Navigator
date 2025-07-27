<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index()
    {
        try {
            $locations = Location::orderBy('name')->get();

            return response()->json([
                'success' => true,
                'locations' => $locations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load locations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($code)
    {
        try {
            $location = Location::where('code', $code)->firstOrFail();

            return response()->json([
                'success' => true,
                'location' => $location
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50|unique:locations,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'active' => 'boolean'
            ]);

            $validated['active'] = $validated['active'] ?? true;

            $location = Location::create($validated);

            return response()->json([
                'success' => true,
                'location' => $location,
                'message' => 'Location created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create location',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $code)
    {
        try {
            $location = Location::where('code', $code)->firstOrFail();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'active' => 'boolean'
            ]);

            $location->update($validated);

            return response()->json([
                'success' => true,
                'location' => $location,
                'message' => 'Location updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update location',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($code)
    {
        try {
            $location = Location::where('code', $code)->firstOrFail();

            // Prevent deletion if location has associated data
            if ($location->hops()->exists() ||
                $location->connections()->exists() ||
                $location->boundaries()->exists() ||
                $location->overlays()->exists()) {
                return response()->json([
                    'error' => 'Cannot delete location with associated data'
                ], 422);
            }

            $location->delete();

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete location',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function activate($code)
    {
        try {
            $location = Location::where('code', $code)->firstOrFail();
            $location->update(['active' => true]);

            return response()->json([
                'success' => true,
                'location' => $location,
                'message' => 'Location activated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to activate location',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deactivate($code)
    {
        try {
            $location = Location::where('code', $code)->firstOrFail();
            $location->update(['active' => false]);

            return response()->json([
                'success' => true,
                'location' => $location,
                'message' => 'Location deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to deactivate location',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
