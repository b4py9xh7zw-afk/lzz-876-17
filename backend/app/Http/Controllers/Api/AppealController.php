<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Models\AppealReview;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AppealController extends Controller
{
    /**
     * 学生提交成绩申诉（选择题目 + 填写原因 + 上传证据）
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'question_id' => 'required|exists:questions,id',
            'appeal_type' => ['required', Rule::in(array_keys(Appeal::TYPES))],
            'reason' => 'required|string|max:1000',
            'evidence' => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:5120',
        ], [
            'exam_record_id.required' => '请选择考试记录',
            'exam_record_id.exists' => '考试记录不存在',
            'question_id.required' => '请选择申诉题目',
            'question_id.exists' => '题目不存在',
            'appeal_type.required' => '请选择申诉类型',
            'appeal_type.in' => '申诉类型无效',
            'reason.required' => '请填写申诉原因',
            'reason.max' => '申诉原因不能超过1000字',
            'evidence.required' => '请上传申诉证据',
            'evidence.mimes' => '证据仅支持 jpg、png、gif、webp、pdf 格式',
            'evidence.max' => '证据文件不能超过5MB',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('status', ExamRecord::STATUS_GRADED)
            ->first();

        if (!$record) {
            return response()->json(['message' => '只能对已评分的本人考试记录发起申诉'], 403);
        }

        $question = $record->examPaper->questions()
            ->where('questions.id', $request->question_id)
            ->first();

        if (!$question) {
            return response()->json(['message' => '该题目不属于此试卷'], 422);
        }

        $existing = Appeal::where('exam_record_id', $record->id)
            ->where('question_id', $request->question_id)
            ->whereIn('status', [Appeal::STATUS_PENDING, Appeal::STATUS_FORWARDED])
            ->first();

        if ($existing) {
            return response()->json(['message' => '该题目已有正在处理中的申诉，请勿重复提交'], 422);
        }

        $file = $request->file('evidence');
        $path = $file->store('appeals', 'local');

        $appeal = Appeal::create([
            'exam_record_id' => $record->id,
            'user_id' => $request->user()->id,
            'question_id' => $request->question_id,
            'appeal_type' => $request->appeal_type,
            'reason' => $request->reason,
            'evidence_path' => $path,
            'evidence_name' => $file->getClientOriginalName(),
            'status' => Appeal::STATUS_PENDING,
            'score_delta' => 0,
        ]);

        return response()->json([
            'message' => '申诉提交成功，请等待老师复核',
            'appeal' => $appeal->load(['question', 'reviews.handler']),
        ], 201);
    }

    /**
     * 学生查看自己的申诉列表（含复核轨迹）
     */
    public function myAppeals(Request $request)
    {
        $appeals = Appeal::with(['question', 'examRecord.examPaper', 'reviews.handler'])
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'appeals' => $appeals,
        ]);
    }

    /**
     * 老师/教务查看申诉列表
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $query = Appeal::with(['user', 'question', 'examRecord.examPaper', 'reviews.handler'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'forwarded' THEN 1 ELSE 2 END")
            ->orderBy('id', 'desc');

        if ($request->filled('status') && array_key_exists($request->status, Appeal::STATUSES)) {
            $query->where('status', $request->status);
        }

        $appeals = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'appeals' => $appeals,
        ]);
    }

    /**
     * 申诉详情（含每个处理人的意见轨迹）
     */
    public function show(Request $request, Appeal $appeal)
    {
        $user = $request->user();

        if ($appeal->user_id !== $user->id && !$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权查看该申诉'], 403);
        }

        $appeal->load(['user', 'question', 'examRecord.examPaper', 'reviews.handler']);

        return response()->json([
            'appeal' => $appeal,
        ]);
    }

    /**
     * 老师/教务复核申诉：维持、加分、减分或转给教务
     */
    public function review(Request $request, Appeal $appeal)
    {
        $user = $request->user();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权复核申诉'], 403);
        }

        if ($appeal->status === Appeal::STATUS_COMPLETED) {
            return response()->json(['message' => '该申诉已复核完成，无需重复处理'], 422);
        }

        if ($appeal->status === Appeal::STATUS_FORWARDED && !$user->isAdmin()) {
            return response()->json(['message' => '该申诉已转交教务，请等待教务处理'], 403);
        }

        $allowedActions = [
            AppealReview::ACTION_MAINTAIN,
            AppealReview::ACTION_ADD_SCORE,
            AppealReview::ACTION_REDUCE_SCORE,
        ];
        if (!$user->isAdmin()) {
            $allowedActions[] = AppealReview::ACTION_FORWARD;
        }

        $validator = Validator::make($request->all(), [
            'action' => ['required', Rule::in($allowedActions)],
            'opinion' => 'required|string|max:1000',
            'score_delta' => 'required_if:action,add_score,reduce_score|nullable|numeric|min:0.01|max:100',
        ], [
            'action.required' => '请选择复核处理方式',
            'action.in' => '复核处理方式无效',
            'opinion.required' => '请填写处理意见',
            'opinion.max' => '处理意见不能超过1000字',
            'score_delta.required_if' => '加分或减分时请填写分数变动值',
            'score_delta.numeric' => '分数变动值必须为数字',
            'score_delta.min' => '分数变动值必须大于0',
            'score_delta.max' => '分数变动值不能超过100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $action = $request->action;

        $appeal = DB::transaction(function () use ($request, $appeal, $user, $action) {
            if ($action === AppealReview::ACTION_FORWARD) {
                $appeal->update(['status' => Appeal::STATUS_FORWARDED]);

                AppealReview::create([
                    'appeal_id' => $appeal->id,
                    'handler_id' => $user->id,
                    'action' => AppealReview::ACTION_FORWARD,
                    'opinion' => $request->opinion,
                    'score_delta' => 0,
                ]);

                return $appeal->fresh();
            }

            $delta = 0;
            if ($action === AppealReview::ACTION_ADD_SCORE) {
                $delta = abs((float) $request->score_delta);
            } elseif ($action === AppealReview::ACTION_REDUCE_SCORE) {
                $delta = -abs((float) $request->score_delta);
            }

            if ($delta != 0) {
                $delta = $this->applyScoreAdjustment($appeal, $delta);
            }

            $appeal->update([
                'status' => Appeal::STATUS_COMPLETED,
                'result' => $action,
                'score_delta' => $delta,
            ]);

            AppealReview::create([
                'appeal_id' => $appeal->id,
                'handler_id' => $user->id,
                'action' => $action,
                'opinion' => $request->opinion,
                'score_delta' => $delta,
            ]);

            return $appeal->fresh();
        });

        $message = $action === AppealReview::ACTION_FORWARD ? '已转给教务处理' : '复核完成';

        return response()->json([
            'message' => $message,
            'appeal' => $appeal->load(['user', 'question', 'examRecord.examPaper', 'reviews.handler']),
        ]);
    }

    /**
     * 下载申诉证据（本人或老师/教务）
     */
    public function evidence(Request $request, Appeal $appeal)
    {
        $user = $request->user();

        if ($appeal->user_id !== $user->id && !$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权查看该证据'], 403);
        }

        if (!Storage::disk('local')->exists($appeal->evidence_path)) {
            return response()->json(['message' => '证据文件不存在'], 404);
        }

        return Storage::disk('local')->download(
            $appeal->evidence_path,
            $appeal->evidence_name ?: 'evidence'
        );
    }

    /**
     * 应用分数调整：更新题目得分并重算总分，返回实际生效的分数变动
     */
    protected function applyScoreAdjustment(Appeal $appeal, float $delta): float
    {
        $record = ExamRecord::with('examPaper.questions')->findOrFail($appeal->exam_record_id);

        $question = $record->examPaper->questions
            ->firstWhere('id', $appeal->question_id);
        $fullScore = $question ? (float) $question->pivot->score : 0;

        $answer = ExamRecordAnswer::where('exam_record_id', $record->id)
            ->where('question_id', $appeal->question_id)
            ->first();

        $oldScore = $answer ? (float) $answer->score : 0;
        $newScore = max(0, min($fullScore, $oldScore + $delta));
        $actualDelta = $newScore - $oldScore;

        if ($answer) {
            $isCorrect = $answer->is_correct;
            if ($fullScore > 0 && $newScore >= $fullScore) {
                $isCorrect = true;
            } elseif ($newScore <= 0) {
                $isCorrect = false;
            }
            $answer->update([
                'score' => $newScore,
                'is_correct' => $isCorrect,
            ]);
        } else {
            ExamRecordAnswer::create([
                'exam_record_id' => $record->id,
                'question_id' => $appeal->question_id,
                'answer' => '',
                'is_correct' => $fullScore > 0 && $newScore >= $fullScore,
                'score' => $newScore,
            ]);
        }

        $totalScore = ExamRecordAnswer::where('exam_record_id', $record->id)->sum('score');
        $record->update(['score' => $totalScore]);

        return $actualDelta;
    }
}
