<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingTestRel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Booking::with([
            'patient',
            'bookingTestRel.test',
            'bookingTestRel.culture',
            'bookingTestRel.package',
            'lab',
        ]);

        // Admin (role_id = 1) can see all bookings
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $bookings = $query->latest()->get();

        if ($bookings && $bookings->count() > 0) {
            $bookings = $bookings->map(function ($booking) {
                $tests = [];
                $cultures = [];
                $packages = [];
                foreach ($booking->bookingTestRel as $bookingTestRel) {
                    if ($bookingTestRel->test) {
                        $tests[] = $bookingTestRel->test;
                    }
                    if ($bookingTestRel->culture) {
                        $cultures[] = $bookingTestRel->culture;
                    }
                    if ($bookingTestRel->package) {
                        $packages[] = $bookingTestRel->package;
                    }
                }

                return [
                    'patients' => $booking->patient,
                    'booking_date' => $booking->booking_date,
                    'address' => $booking->address,
                    'at_home' => $booking->at_home,
                    'prescription' => $booking->prescription,
                    'lab' => $booking->lab?->name,
                    'lng' => $booking->lng,
                    'lat' => $booking->lat,
                    'tests' => $tests,
                    'cultures' => $cultures,
                    'packages' => $packages,
                ];
            });
        }

        return response()->json($bookings);
    }

    /**
     * Store a new booking.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id_fk' => 'required|exists:patients,id',
            'prescription' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'address' => 'nullable|string|max:255',
            'at_home' => 'boolean',
            'lng' => 'nullable|string',
            'lat' => 'nullable|string',
            'booking_date' => 'required|date',
            'tests' => 'nullable|array',
            'cultures' => 'nullable|array',
            'packages' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Handle prescription file upload
        if ($request->hasFile('prescription')) {
            $file = $request->file('prescription');
            $filename = \Illuminate\Support\Str::uuid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('prescriptions', $filename, 'public');
            $data['prescription'] = config('app.url') . Storage::url($filename);
        }

        // Create booking
        $booking = Booking::create([
            'patient_id_fk' => $data['patient_id_fk'],
            'prescription' => $data['prescription'] ?? null,
            'address' => $data['address'] ?? null,
            'at_home' => $data['at_home'] ?? false,
            'lng' => $data['lng'] ?? null,
            'lat' => $data['lat'] ?? null,
            'booking_date' => $data['booking_date'],
            'lab_id_fk' => auth()->user()->id,
        ]);

        // Create test relations
        if (!empty($data['cultures'])) {
            foreach ($data['cultures'] as $culture) {
                BookingTestRel::create([
                    'booking_id_fk' => $booking->id,
                    'test_id_fk' => null,
                    'culture_id_fk' => $culture['culture_id_fk'] ?? null,
                    'package_id_fk' => null,
                ]);
            }
        }
        if (!empty($data['tests'])) {
            foreach ($data['tests'] as $test) {
                BookingTestRel::create([
                    'booking_id_fk' => $booking->id,
                    'test_id_fk' => $test['test_id_fk'] ?? null,
                    'culture_id_fk' => null,
                    'package_id_fk' => null,
                ]);
            }
        }
        if (!empty($data['packages'])) {
            foreach ($data['packages'] as $package) {
                BookingTestRel::create([
                    'booking_id_fk' => $booking->id,
                    'test_id_fk' => null,
                    'culture_id_fk' => null,
                    'package_id_fk' => $package['package_id_fk'] ?? null,
                ]);
            }
        }

        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking,
        ], 201);
    }

    /**
     * Display the specified booking.
     */
    public function show(Request $request)
    {
        $booking = Booking::with([
            'patient',
            'bookingTestRel.test',
            'bookingTestRel.culture',
            'bookingTestRel.package',
        ])->find($request->id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $booking->lab_id_fk != $authUser->id && $booking->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tests = [];
        $cultures = [];
        $packages = [];
        foreach ($booking->bookingTestRel as $bookingTestRel) {
            if ($bookingTestRel->test) {
                $tests[] = $bookingTestRel->test;
            }
            if ($bookingTestRel->culture) {
                $cultures[] = $bookingTestRel->culture;
            }
            if ($bookingTestRel->package) {
                $packages[] = $bookingTestRel->package;
            }
        }
        $booking = [
            'patients' => $booking->patient,
            'booking_date' => $booking->booking_date,
            'address' => $booking->address,
            'at_home' => $booking->at_home,
            'prescription' => $booking->prescription,
            'lng' => $booking->lng,
            'lat' => $booking->lat,
            'tests' => $tests,
            'cultures' => $cultures,
            'packages' => $packages,
        ];

        return response()->json($booking);
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request)
    {
        $booking = Booking::find($request->id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $booking->lab_id_fk != $authUser->id && $booking->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id_fk' => 'exists:patients,id',
            'prescription' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'address' => 'nullable|string|max:255',
            'at_home' => 'boolean',
            'lng' => 'nullable|string',
            'lat' => 'nullable|string',
            'booking_date' => 'date',
            'tests' => 'nullable|array',
            'cultures' => 'nullable|array',
            'packages' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Handle prescription file upload
        if ($request->hasFile('prescription')) {
            if ($booking->prescription) {
                Storage::disk('public')->delete('prescriptions/' . $booking->prescription);
            }
            $file = $request->file('prescription');
            $filename = \Illuminate\Support\Str::uuid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('prescriptions', $filename, 'public');
            $data['prescription'] = $filename;
        }

        // Update booking
        $booking->update([
            'patient_id_fk' => $data['patient_id_fk'] ?? $booking->patient_id_fk,
            'prescription' => $data['prescription'] ?? $booking->prescription,
            'address' => $data['address'] ?? $booking->address,
            'at_home' => $data['at_home'] ?? $booking->at_home,
            'lng' => $data['lng'] ?? $booking->lng,
            'lat' => $data['lat'] ?? $booking->lat,
            'booking_date' => $data['booking_date'] ?? $booking->booking_date,
        ]);

        $booking->bookingTestRels()->delete();
        // Update test relations
        if (!empty($data['tests'])) {
            // Create new relations
            foreach ($data['tests'] as $test) {
                BookingTestRel::create([
                    'booking_id_fk' => $booking->id,
                    'test_id_fk' => $test['test_id_fk'] ?? null,
                    'culture_id_fk' => null,
                    'package_id_fk' => null,
                ]);
            }
        }
        if (!empty($data['cultures'])) {
            foreach ($data['cultures'] as $culture) {
                BookingTestRel::create([
                    'booking_id_fk' => $booking->id,
                    'test_id_fk' => null,
                    'culture_id_fk' => $culture['culture_id_fk'] ?? null,
                    'package_id_fk' => null,
                ]);
            }
        }
        if (!empty($data['packages'])) {
            foreach ($data['packages'] as $package) {
                BookingTestRel::create([
                    'booking_id_fk' => $booking->id,
                    'test_id_fk' => null,
                    'culture_id_fk' => null,
                    'package_id_fk' => $package['package_id_fk'] ?? null,
                ]);
            }
        }

        return response()->json([
            'message' => 'Booking updated successfully',
            'booking' => $booking->load([
                'patient',
                'bookingTestRels.test',
                'bookingTestRels.culture',
                'bookingTestRels.package',
            ]),
        ]);
    }

    /**
     * Remove the specified booking.
     */
    public function destroy(Request $request)
    {
        $booking = Booking::findOrFail($request->id);

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $booking->lab_id_fk != $authUser->id && $booking->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete prescription file if exists
        if ($booking->prescription) {
            Storage::disk('public')->delete('prescriptions/' . $booking->prescription);
        }

        // Delete test relations
        $booking->bookingTestRels()->delete();

        // Delete booking
        $booking->delete();

        return response()->json([
            'message' => 'Booking deleted successfully',
        ]);
    }

    /**
     * Get bookings for a specific patient.
     */
    public function getPatientBookings($patientId)
    {
        $authUser = Auth::user();
        $query = Booking::where('patient_id_fk', $patientId)->with('patient');

        if ($authUser->role_id != 1) {
            $query->where(function ($q) use ($authUser) {
                $q->where('lab_id_fk', $authUser->id)->orWhere('lab_id_fk', $authUser->creator_id);
            });
        }

        $bookings = $query->latest()->get();

        return response()->json($bookings);
    }
}
