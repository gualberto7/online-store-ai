<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ReportAssistant;
use App\Ai\Tools\QueryStoreReports;
use App\Http\Requests\AskReportAssistantRequest;
use App\Models\Branch;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ReportAssistantController extends Controller
{
    public function __invoke(AskReportAssistantRequest $request, Team $currentTeam): JsonResponse
    {
        $provider = config('reports-ai.provider');
        if (! config('reports-ai.enabled') || (! ReportAssistant::isFaked() && blank(config("ai.providers.{$provider}.key")))) {
            return response()->json(['message' => 'La consulta con IA no está configurada. Configura la credencial del proveedor de reportes.'], 503);
        }
        $lock = Cache::lock('report-ai:'.$currentTeam->id.':'.$request->user()->id, 180);
        if (! $lock->get()) {
            return response()->json(['message' => 'Ya hay una consulta en curso. Espera a que termine.'], 409);
        }
        try {
            $from = $request->string('from')->toString();
            $to = $request->string('to')->toString();
            $tool = new QueryStoreReports($currentTeam, $request->user(), $from, $to, $request->integer('branch'), $request->input('stock') === 'empty');
            $branches = $currentTeam->branches()->orderBy('name')->get(['id', 'name'])->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name])->values()->all();
            $agent = new ReportAssistant($tool, $from, $to, array_values($branches));
            $response = $agent->prompt($request->string('prompt')->trim()->toString(), provider: $provider, model: config('reports-ai.model'), timeout: 30);

            return response()->json(['answer' => $response->text, 'reports' => $tool->reports]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No se pudo completar la consulta con IA. Revisa la credencial y el saldo del proveedor o vuelve a intentarlo.'], 503);
        } finally {
            $lock->release();
        }
    }
}
