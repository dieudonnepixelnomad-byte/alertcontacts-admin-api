<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     */
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $validated = $request->validated();
            
            $feedback = Feedback::create([
                'user_id' => $user->id,
                'type' => $validated['type'],
                'subject' => $validated['subject'] ?? null,
                'message' => $validated['message'],
                'rating' => $validated['rating'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'device_info' => $validated['device_info'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre feedback a été envoyé avec succès. Merci pour votre retour !',
                'data' => $feedback,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de votre feedback.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
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
