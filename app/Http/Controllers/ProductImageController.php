<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductImagesRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class ProductImageController extends Controller
{
    public function store(StoreProductImagesRequest $request, Team $currentTeam, Product $product): JsonResponse|RedirectResponse
    {
        $file = $request->file('image');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->store("teams/{$currentTeam->id}/products/{$product->id}", 'public');
        if ($path === false) {
            throw ValidationException::withMessages(['image' => 'No se pudo guardar la imagen.']);
        }

        try {
            $image = DB::transaction(function () use ($product, $path): ProductImage {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                if ($product->images()->count() >= 10) {
                    throw ValidationException::withMessages(['image' => 'Cada producto admite hasta 10 imágenes.']);
                }

                return $product->images()->create(['path' => $path]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json(['image' => $image->only(['id', 'url'])], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagen agregada.']);

        return to_route('products.show', ['current_team' => $currentTeam, 'product' => $product]);
    }

    public function destroy(Team $currentTeam, Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);
        DB::transaction(function () use ($product, $image): void {
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $image->delete();
        });
        Storage::disk('public')->delete($image->path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagen eliminada.']);

        return to_route('products.show', ['current_team' => $currentTeam, 'product' => $product]);
    }
}
