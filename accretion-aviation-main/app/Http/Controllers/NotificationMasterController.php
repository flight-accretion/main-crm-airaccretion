<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationMaster;
use Propaganistas\LaravelPhone\Rules\Phone;

class NotificationMasterController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationMaster::query();
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        if ($request->filled('contact_number')) {
            $query->where('mobile_number', 'like', '%' . $request->contact_number . '%');
        }
        if ($request->filled('contact_country_code')) {
            $query->where('contact_country_code', 'like', '%' . $request->contact_country_code . '%');
        }
        if ($request->filled('email')) {
            $query->where('email_id', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('status')) {
            $statusVal = $request->status === 'active' ? 1 : 0;
            $query->where('status', $statusVal);
        }

        $masters = $query->latest()
            ->paginate($perPage)
            ->appends($request->query());
        return view('admin.notification_master.index', compact('masters'));
    }

    public function store(Request $request)
    {
        // country_iso comes from the hidden field populated by intl-tel-input (e.g. "us", "in", "gb")
        $countryIso = strtoupper($request->input('country_iso', 'IN'));

        $request->validate([
            'contact_country_code' => 'required|string|max:8',
            'country_iso'          => 'required|string|size:2',

            // propaganistas/laravel-phone — uses Google libphonenumber.
            // Note: .type('mobile') intentionally omitted because many countries
            // (e.g. US, Canada) do not distinguish mobile from landline numbers
            // in libphonenumber, which would cause valid numbers to be rejected.
            'contact_number' => [
                'required',
                (new Phone)->country($countryIso),
            ],
            'email' => 'nullable|email',
        ], [
            'contact_number.phone' => 'The phone number is not valid for the selected country (' . $countryIso . ').',
        ]);

        NotificationMaster::create([
            'contact_country_code' => $request->input('contact_country_code'),
            'mobile_number'        => $request->input('contact_number'),
            'email_id'             => $request->input('email'),
            'country_iso'          => strtolower($countryIso),
            'status'               => 1,
        ]);

        return redirect()->route('admin.notification-master.index')
            ->with('success', 'Notification member added successfully.');
    }

    public function edit($id)
    {
        $master = NotificationMaster::findOrFail($id);
        return response()->json(['master' => $master]);
    }

    public function update(Request $request, $id)
    {
        $countryIso = strtoupper($request->input('country_iso', 'IN'));

        $request->validate([
            'contact_country_code' => 'required|string|max:8',
            'country_iso'          => 'required|string|size:2',
            'mobile_number' => [
                'required',
                (new Phone)->country($countryIso),
            ],
            'email_id' => 'nullable|email',
            'status'   => 'required|in:active,inactive',
        ], [
            'mobile_number.phone' => 'The phone number is not valid for the selected country (' . $countryIso . ').',
        ]);

        $master = NotificationMaster::findOrFail($id);

        $master->update([
            'contact_country_code' => $request->contact_country_code,
            'mobile_number'        => $request->mobile_number,
            'email_id'             => $request->email_id,
            'country_iso'          => strtolower($countryIso),
            'status'               => $request->input('status') === 'active' ? 1 : 0,
        ]);

        // Return the updated model so frontend can refresh UI without a full page reload
        $master = $master->fresh();
        return response()->json(['success' => true, 'message' => 'Updated successfully.', 'master' => $master]);
    }

    public function destroy($id)
    {
        $master = NotificationMaster::findOrFail($id);
        $master->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
    }
}
