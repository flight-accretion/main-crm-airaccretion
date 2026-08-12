<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadTransfer;
use App\Models\User;
use App\Services\LeadTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeadTransferController extends Controller
{
    public function store(
        Request $request,
        Lead $lead,
        LeadTransferService $service
    ) {
        $validated = $request->validate([
            'to_user_id' =>
                'required|uuid|exists:users,id',

            'reason' =>
                'nullable|string|max:1000',
        ]);

        try {
            $toUser = User::with('userType')
                ->findOrFail(
                    $validated['to_user_id']
                );

            $service->requestTransfer(
                $lead,
                auth()->user(),
                $toUser,
                $validated['reason'] ?? null
            );

            return back()->with(
                'success',
                'Lead transfer request sent successfully. The lead will remain with the current salesperson until the new salesperson accepts it.'
            );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            \Log::error(
                'Lead transfer request failed',
                [
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'error' => $e->getMessage(),
                ]
            );

            return back()->with(
                'error',
                'The transfer request could not be created. Please try again.'
            );
        }
    }

    public function accept(
        LeadTransfer $transfer,
        LeadTransferService $service
    ) {
        try {
            $service->accept(
                $transfer,
                auth()->user()
            );

            return back()->with(
                'success',
                'Lead transfer accepted. This lead is now assigned to you.'
            );
        } catch (ValidationException $e) {
            return back()->withErrors(
                $e->errors()
            );
        } catch (\Throwable $e) {
            \Log::error(
                'Lead transfer acceptance failed',
                [
                    'transfer_id' =>
                        $transfer->id,

                    'user_id' =>
                        auth()->id(),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->with(
                'error',
                'The lead transfer could not be accepted. Please try again.'
            );
        }
    }

    public function reject(
        Request $request,
        LeadTransfer $transfer,
        LeadTransferService $service
    ) {
        $validated = $request->validate([
            'response_note' =>
                'nullable|string|max:1000',
        ]);

        try {
            $service->reject(
                $transfer,
                auth()->user(),
                $validated['response_note'] ?? null
            );

            return back()->with(
                'success',
                'Lead transfer rejected. The existing salesperson remains assigned.'
            );
        } catch (ValidationException $e) {
            return back()->withErrors(
                $e->errors()
            );
        } catch (\Throwable $e) {
            \Log::error(
                'Lead transfer rejection failed',
                [
                    'transfer_id' =>
                        $transfer->id,

                    'user_id' =>
                        auth()->id(),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->with(
                'error',
                'The lead transfer could not be rejected. Please try again.'
            );
        }
    }
}