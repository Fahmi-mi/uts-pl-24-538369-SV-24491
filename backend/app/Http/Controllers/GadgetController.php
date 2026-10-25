<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGadgetRequest;
use App\Http\Requests\UpdateGadgetRequest;
use App\Http\Resources\GadgetResource;
use App\Http\Resources\PriceHistoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GadgetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->gadgets();

        $query->when($request->filled('category'), function ($q) use ($request) {
            $q->where('category', $request->query('category'));
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->query('status'));
        });

        $query->when($request->filled('priority'), function ($q) use ($request) {
            $q->where('priority', $request->query('priority'));
        });

        $query->when($request->filled('search'), function ($q) use ($request) {
            $term = '%' . $request->query('search') . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('brand', 'like', $term);
            });
        });

        $gadgets = $query->latest()->paginate(10);

        return GadgetResource::collection($gadgets);
    }

    public function store(StoreGadgetRequest $request): JsonResponse
    {
        $gadget = DB::transaction(function () use ($request) {
            $gadget = $request->user()->gadgets()->create($request->validated());
            $gadget->priceHistories()->create([
                'price' => $gadget->current_price,
            ]);

            return $gadget;
        });

        return response()->json([
            'message' => 'Gadget berhasil ditambahkan',
            'data' => new GadgetResource($gadget),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $gadget = $request->user()->gadgets()->findOrFail($id);

        return response()->json([
            'data' => new GadgetResource($gadget),
        ], 200);
    }

    public function update(UpdateGadgetRequest $request, int $id): JsonResponse
    {
        $gadget = $request->user()->gadgets()->findOrFail($id);

        $gadget = DB::transaction(function () use ($gadget, $request) {
            $gadget->fill($request->validated());
            $priceChanged = $gadget->isDirty('current_price');
            $gadget->save();

            if ($priceChanged) {
                $gadget->priceHistories()->create([
                    'price' => $gadget->current_price,
                ]);
            }

            return $gadget;
        });

        return response()->json([
            'message' => 'Gadget berhasil diperbarui',
            'data' => new GadgetResource($gadget),
        ], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $gadget = $request->user()->gadgets()->findOrFail($id);
        $gadget->delete();

        return response()->json([
            'message' => 'Gadget berhasil dihapus',
        ], 200);
    }

    public function priceHistories(Request $request, int $id): JsonResponse
    {
        $gadget = $request->user()->gadgets()->findOrFail($id);
        $histories = $gadget->priceHistories()->orderByDesc('recorded_at')->get();

        return response()->json([
            'data' => PriceHistoryResource::collection($histories),
        ], 200);
    }
}
