<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairImage;
use App\Models\RepairRequest;
use App\Models\ShopDocument;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Models\TechnicianDocument;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams files from the private disk after authorisation checks. */
class PrivateFileController extends Controller
{
    public function repairImage(Request $request, RepairImage $image): StreamedResponse
    {
        Gate::authorize('view', RepairRequest::findOrFail($image->repair_request_id));

        return Storage::disk('local')->response($image->path);
    }

    public function ticketAttachment(Request $request, SupportAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', SupportTicket::findOrFail($attachment->support_ticket_id));

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function shopDocument(Request $request, ShopDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $document->shop()->where('merchant_id', $user->id)->exists(), 403);
        if ($user->isAdmin()) {
            AuditLogger::log('document.viewed', $document->shop, "Viewed shop document #{$document->id} ({$document->type})");
        }

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function technicianDocument(Request $request, TechnicianDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $document->technicianProfile?->user_id === $user->id, 403);
        if ($user->isAdmin()) {
            AuditLogger::log('document.viewed', $document->technicianProfile, "Viewed technician document #{$document->id} ({$document->type})");
        }

        return Storage::disk('local')->download($document->path, $document->original_name);
    }
}
