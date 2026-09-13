<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'user_id',
        'question_id',
        'appeal_type',
        'reason',
        'evidence_path',
        'evidence_name',
        'status',
        'result',
        'score_delta',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'user_id' => 'integer',
        'question_id' => 'integer',
        'appeal_type' => 'string',
        'status' => 'string',
        'result' => 'string',
        'score_delta' => 'decimal:2',
    ];

    public const TYPE_SCORE = 'score';
    public const TYPE_GRADING = 'grading';
    public const TYPE_ANOMALY = 'anomaly';

    public const TYPES = [
        self::TYPE_SCORE => '分数异议',
        self::TYPE_GRADING => '判题异议',
        self::TYPE_ANOMALY => '异常标记异议',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_FORWARDED = 'forwarded';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING => '待复核',
        self::STATUS_FORWARDED => '已转教务',
        self::STATUS_COMPLETED => '复核完成',
    ];

    public const RESULT_MAINTAIN = 'maintain';
    public const RESULT_ADD_SCORE = 'add_score';
    public const RESULT_REDUCE_SCORE = 'reduce_score';

    public const RESULTS = [
        self::RESULT_MAINTAIN => '维持原判',
        self::RESULT_ADD_SCORE => '加分',
        self::RESULT_REDUCE_SCORE => '减分',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function reviews()
    {
        return $this->hasMany(AppealReview::class, 'appeal_id')->orderBy('id');
    }
}
