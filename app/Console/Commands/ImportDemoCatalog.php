<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Team;
use Database\Seeders\DemoGiftProductSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportDemoCatalog extends Command
{
    protected $signature = 'catalog:import-demo {--images : Descargar las fotos de los productos de demostración}';

    protected $description = 'Importa 100 productos de ropa, accesorios y regalos de prueba sin sobrescribir cambios';

    public function handle(DemoGiftProductSeeder $seeder): int
    {
        $team = Team::query()->where('slug', 'tienda-demo')->where('is_personal', false)->first();
        if ($team === null) {
            $this->error('Primero crea la empresa de demostración con php artisan db:seed.');

            return self::FAILURE;
        }
        $products = $seeder->import($team);
        $this->info('Productos de demostración disponibles: '.count($products).'.');
        if (! $this->option('images')) {
            return self::SUCCESS;
        }
        $tasks = [];
        foreach ($seeder->catalog() as $data) {
            $product = $products[$data['source_id']];
            $base = "teams/{$team->id}/products/{$product->id}/demo-dummyjson-{$data['source_id']}";
            $existing = $product->images()->whereIn('path', [$base.'.webp', $base.'.png', $base.'.jpg'])->first();
            if (($existing !== null && Storage::disk('public')->exists($existing->path)) || $product->images()->count() >= 10) {
                continue;
            }
            if (parse_url($data['image_url'], PHP_URL_SCHEME) !== 'https' || parse_url($data['image_url'], PHP_URL_HOST) !== 'cdn.dummyjson.com') {
                $this->error('El catálogo contiene una URL de imagen no permitida.');

                return self::FAILURE;
            }
            $tasks[$data['source_id']] = ['product' => $product, 'url' => $data['image_url'], 'base' => $base];
        }
        $saved = 0;
        $failed = 0;
        foreach (array_chunk($tasks, 8, preserve_keys: true) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch): array {
                $requests = [];
                foreach ($batch as $id => $task) {
                    $requests[] = $pool->as((string) $id)->connectTimeout(5)->timeout(20)
                        ->withOptions(['allow_redirects' => false])->get($task['url']);
                }

                return $requests;
            });
            foreach ($batch as $id => $task) {
                $response = $responses[$id] ?? null;
                try {
                    if (! $response instanceof Response || ! $response->successful() || ! $this->saveImage($task['product'], $task['base'], $response->body())) {
                        $failed++;
                        $this->warn("No se pudo importar la imagen del producto de muestra {$id}.");
                    } else {
                        $saved++;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                    $this->warn("Falló el guardado de la imagen del producto de muestra {$id}.");
                }
            }
            $this->line("Imágenes guardadas: {$saved}; fallidas: {$failed}.");
        }
        $this->info("Importación terminada. Imágenes nuevas o recuperadas: {$saved}; fallidas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function saveImage(Product $product, string $base, string $body): bool
    {
        if (strlen($body) > 1024 * 1024) {
            return false;
        }
        $image = @getimagesizefromstring($body);
        if ($image === false || $image[0] > 4096 || $image[1] > 4096) {
            return false;
        }
        $extension = match ($image[2]) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => null,
        };
        if ($extension === null) {
            return false;
        }
        $path = $base.'.'.$extension;
        if (! Storage::disk('public')->put($path, $body)) {
            return false;
        }
        try {
            DB::transaction(function () use ($product, $path): void {
                $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                if ($locked->images()->where('path', $path)->exists()) {
                    return;
                }
                if ($locked->images()->count() >= 10) {
                    throw new \RuntimeException('El producto ya tiene diez imágenes.');
                }
                $locked->images()->create(['path' => $path]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return true;
    }
}
