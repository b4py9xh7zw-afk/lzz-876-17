<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppealReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'appeal_id',
        'handler_id',
        'action',
        'opinion',
        'score_delta',
    ];

    protected $casts = [
        'appeal_id' => 'integer',
        'handler_id' => 'integer',
        'action' => 'string',
        'score_delta' => 'decimal:2',
    ];

    public const ACTION_MAINTAIN = 'maintain';
    public const ACTION_ADD_SCORE = 'add_score';
    public const ACTION_REDUCE_SCORE = 'reduce_score';
    public const ACTION_FORWARD = 'forward';

    public const ACTIONS = [
        self::ACTION_MAINTAIN => '维持原判',
        self::ACTION_ADD_SCORE => '加分',
        self::ACTION_REDUCE_SCORE => '减分',
        self::ACTION_FORWARD => '转给教务',
    ];

    public function appeal()
    {
        return $this->belongsTo(Appeal::class, 'appeal_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
}
