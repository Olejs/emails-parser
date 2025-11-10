<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Http\Resources\EmailCollection;
use App\Http\Resources\EmailResource;
use App\Models\SuccessfulEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function index(Request $request): EmailCollection
    {
        $perPage = $request->input('per_page', 10);

        $emails = SuccessfulEmail::query()
            ->when($request->has('affiliate_id'), fn($q) =>
                $q->fromAffiliate($request->affiliate_id)
            )
            ->when($request->has('search'), fn($q) =>
                $q->searchInContent($request->search)
            )
            ->when($request->boolean('processed'), fn($q) =>
                $q->processed()
            )
            ->when($request->boolean('unprocessed'), fn($q) =>
                $q->unprocessed()
            )
            ->latest('timestamp')
            ->paginate($perPage);

        return new EmailCollection($emails);
    }

    public function store(StoreEmailRequest $request): JsonResponse
    {
        $email = SuccessfulEmail::create($request->validated());

        return response()->json([
            'message' => 'Email created and parsed successfully',
            'data' => new EmailResource($email)
        ], 201);
    }

    public function show(SuccessfulEmail $email): EmailResource
    {
        return new EmailResource($email);
    }

    public function update(
        UpdateEmailRequest $request,
        SuccessfulEmail $email
    ): JsonResponse
    {
        $updated = $email->update($request->validated());

        return response()->json([
            'message' => 'Email updated successfully',
            'data' => new EmailResource($updated)
        ]);
    }

    public function destroy(SuccessfulEmail $email): JsonResponse
    {
        $email->delete();

        return response()->json([
            'message' => 'Email deleted successfully'
        ]);
    }
}
