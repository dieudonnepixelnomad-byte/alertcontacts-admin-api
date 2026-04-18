<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    public function __construct()
    {
        // Middleware sera défini dans les routes
    }

    /**
     * Display a listing of the user's feedback.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $feedbacks = Feedback::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $feedbacks,
        ]);
    }

        /**
     * Store a newly created feedback in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:feature,bug,compliment,complaint,other',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:20',
            'app_version' => 'nullable|string|max:50',
            'os_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        try {
            Feedback::create([
                'user_id' => $user->id,
                'category' => $request->input('category'),
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'app_version' => $request->input('app_version'),
                'os_version' => $request->input('os_version'),
            ]);

            return response()->json(['message' => 'Feedback submitted successfully.'], 201);

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Feedback submission failed: ' . $e->getMessage());

            return response()->json(['message' => 'An error occurred while submitting feedback.'], 500);
        }
    }

    /**
     * Display the specified feedback.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        $feedback = Feedback::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $feedback,
        ]);
    }

    /**
     * Get feedback types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Feedback::TYPES,
        ]);
    }

    /**
     * Get feedback statistics for the user.
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'total' => Feedback::where('user_id', $user->id)->count(),
            'pending' => Feedback::where('user_id', $user->id)->where('status', 'pending')->count(),
            'reviewed' => Feedback::where('user_id', $user->id)->where('status', 'reviewed')->count(),
            'resolved' => Feedback::where('user_id', $user->id)->where('status', 'resolved')->count(),
            'by_type' => Feedback::where('user_id', $user->id)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
