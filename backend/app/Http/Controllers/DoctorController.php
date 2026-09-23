<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorBookingRequest;
use App\Services\InventoryService;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorController extends Controller
{
    use SecureFileUpload;

    private function labId(): int
    {
        return app(InventoryService::class)->labId(Auth::user());
    }

    public function index(Request $request)
    {
        $query = Doctor::where('lab_id_fk', $this->labId())->orderBy('sort_order')->orderBy('name');
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'links' => 'nullable|array',
            'links.website' => 'nullable|string|max:255',
            'links.facebook' => 'nullable|string|max:255',
            'links.instagram' => 'nullable|string|max:255',
            'links.twitter' => 'nullable|string|max:255',
            'links.tiktok' => 'nullable|string|max:255',
            'bookable' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $result = $this->secureUploadImage($request->file('photo'), 'doctors');
            if (! $result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $photoPath = $result['path'];
        }

        $doctor = Doctor::create([
            ...$validated,
            'lab_id_fk' => $this->labId(),
            'photo' => $photoPath,
        ]);

        return response()->json($doctor, 201);
    }

    public function update(Request $request, $id)
    {
        $doctor = Doctor::where('lab_id_fk', $this->labId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'links' => 'nullable|array',
            'links.website' => 'nullable|string|max:255',
            'links.facebook' => 'nullable|string|max:255',
            'links.instagram' => 'nullable|string|max:255',
            'links.twitter' => 'nullable|string|max:255',
            'links.tiktok' => 'nullable|string|max:255',
            'bookable' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            if ($doctor->getRawOriginal('photo')) {
                $this->safeDeleteFile($doctor->getRawOriginal('photo'));
            }
            $result = $this->secureUploadImage($request->file('photo'), 'doctors');
            if (! $result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $validated['photo'] = $result['path'];
        }

        $doctor->update($validated);

        return response()->json($doctor);
    }

    public function destroy($id)
    {
        $doctor = Doctor::where('lab_id_fk', $this->labId())->findOrFail($id);
        if ($doctor->getRawOriginal('photo')) {
            $this->safeDeleteFile($doctor->getRawOriginal('photo'));
        }
        $doctor->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /**
     * Staff-side: booking requests submitted by patients from the portal.
     */
    public function bookingRequests(Request $request)
    {
        $query = DoctorBookingRequest::with('doctor:id,name,specialty')
            ->where('lab_id_fk', $this->labId())
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function updateBookingRequest(Request $request, $id)
    {
        $validated = $request->validate(['status' => 'required|in:pending,confirmed,cancelled']);
        $booking = DoctorBookingRequest::where('lab_id_fk', $this->labId())->findOrFail($id);
        $booking->update($validated);

        return response()->json($booking);
    }
}
