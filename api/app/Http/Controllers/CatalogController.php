<?php

namespace App\Http\Controllers;

use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Testimonial;
use App\Services\LotteryRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index(LotteryRules $rules): JsonResponse {
        $games = LotteryGame::query()->where('active', DB::raw('true'))->with(['draws'=>fn ($q) => $q->where('status','open')->orderBy('draw_at')->limit(1)])->get()->map(function (LotteryGame $game) use ($rules) {
            $next = $game->draws->first();
            $definition = $rules->definition($game);
            $minimumCount = (int) $definition['min_numbers'];
            return ['id'=>$game->id,'slug'=>$game->slug,'name'=>$game->name,'short_name'=>$game->short_name,'price_cents'=>$rules->priceFor($game, $minimumCount),'color'=>$game->color,'number_min'=>$definition['range_min'],'range_max'=>$definition['range_max'],'numbers_required'=>$game->numbers_required,'min_numbers'=>$definition['min_numbers'],'max_numbers'=>$definition['max_numbers'],'price_table'=>$definition['price_table'],'official_price_table'=>$definition['official_price_table'],'selection_mode'=>$game->selection_mode,'special_options'=>array_merge($game->special_options ?? [], ['special_type'=>$definition['special_type'] ?? null, 'columns'=>$definition['columns'] ?? null]),'rules_source_url'=>$game->rules_source_url ?: ($definition['source_url'] ?? null),'next_draw'=>$next ? ['id'=>$next->id,'contest_number'=>$next->contest_number,'draw_at'=>$next->draw_at,'sales_close_at'=>$next->sales_close_at] : null];
        });
        return response()->json(['data'=>$games]);
    }
    public function pools(): JsonResponse
    {
        $pools = LotteryPool::query()->with(['game','draw'])->where('status','open')->whereHas('draw', fn ($query) => $query->where('status','open')->where('draw_at','>',now()))->latest()->get()->map(fn (LotteryPool $pool) => [
            'id'=>$pool->id,'slug'=>$pool->game->slug,'game'=>$pool->game->name,'title'=>$pool->name,'description'=>$pool->description,'price_cents'=>$pool->share_price_cents,'total_shares'=>$pool->total_shares,'sold_shares'=>$pool->sold_shares,'reserved_shares'=>$pool->reserved_shares,'numbers_count'=>$pool->numbers_count,'lines'=>$pool->lines ?? [],'draw'=>['id'=>$pool->draw->id,'contest_number'=>$pool->draw->contest_number,'draw_at'=>$pool->draw->draw_at,'sales_close_at'=>$pool->draw->sales_close_at],
        ]);
        return response()->json(['data'=>$pools]);
    }
    public function testimonials(): JsonResponse { return response()->json(['data'=>Testimonial::query()->where('active',DB::raw('true'))->where('is_demo',DB::raw('true'))->latest()->get()]); }
}
