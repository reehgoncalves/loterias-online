<?php

namespace App\Http\Controllers;

use App\Models\LotteryGame;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function index(): JsonResponse {
        $games = LotteryGame::query()->where('active', true)->with(['draws'=>fn ($q) => $q->where('status','open')->orderBy('draw_at')->limit(1)])->get()->map(function (LotteryGame $game) {
            $next = $game->draws->first();
            return ['id'=>$game->id,'slug'=>$game->slug,'name'=>$game->name,'short_name'=>$game->short_name,'price_cents'=>$game->price_cents,'color'=>$game->color,'number_min'=>$game->number_min,'range_max'=>$game->range_max,'numbers_required'=>$game->numbers_required,'selection_mode'=>$game->selection_mode,'special_options'=>$game->special_options,'next_draw'=>$next ? ['id'=>$next->id,'contest_number'=>$next->contest_number,'draw_at'=>$next->draw_at] : null];
        });
        return response()->json(['data'=>$games]);
    }
    public function testimonials(): JsonResponse { return response()->json(['data'=>Testimonial::query()->where('active',true)->where('is_demo',true)->latest()->get()]); }
}
