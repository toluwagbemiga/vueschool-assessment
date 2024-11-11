<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RequestLog; // Import the RequestLog model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserUpdateController extends Controller
{
    const MAX_BATCH_REQUESTS_PER_HOUR = 50;
    const MAX_BATCH_SIZE = 1000;
    const MAX_INDIVIDUAL_REQUESTS_PER_HOUR = 3600;

    public function updateUsers(Request $request)
    {
        // Fetch users whose attributes have changed
        $users = User::where('needs_update', true)->get();

        // Prepare batches
        $batches = [];
        foreach ($users as $user) {
            $subscriberData = [
                'email' => $user->email,
                'time_zone' => $user->timezone,
                'name' => $user->firstname . ' ' . $user->lastname,
            ];

            // Create a new batch if needed
            if (count($batches) === 0 || count($batches[count($batches) - 1]['subscribers']) >= self::MAX_BATCH_SIZE) {
                $batches[] = ['subscribers' => []];
            }
            $batches[count($batches) - 1]['subscribers'][] = $subscriberData;
        }

        // Rate limiting checks for batch requests
        if (!$this->canSendBatchRequests(count($batches))) {
            return response()->json(['message' => 'Rate limit exceeded for batch requests.'], 429);
        }

        // Send batches to the third-party API
        foreach ($batches as $batch) {
            $this->sendBatchToApi($batch);
            // Log the request
            $this->logRequest('batch', count($batch['subscribers']));
        }

        // Now handle individual requests for the users
        foreach ($users as $user) {
            // Rate limiting checks for individual requests
            if (!$this->canSendIndividualRequests()) {
                return response()->json(['message' => 'Rate limit exceeded for individual requests.'], 429);
            }

            $this->sendIndividualToApi($user);
            // Log the individual request
            $this->logRequest('individual');
        }

        return response()->json(['message' => 'User updates sent successfully.']);
    }

    private function canSendBatchRequests($batchCount)
    {
        // Get the count of batch requests made in the last hour
        $count = RequestLog::where('type', 'batch')
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        return ($count + 1) <= self::MAX_BATCH_REQUESTS_PER_HOUR; // +1 for the current batch request
    }

    private function canSendIndividualRequests()
    {
        // Get the count of individual requests made in the last hour
        $count = RequestLog::where('type', 'individual')
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        return ($count + 1) <= self::MAX_INDIVIDUAL_REQUESTS_PER_HOUR; // +1 for the current individual request
    }

    private function sendBatchToApi(array $batch)
    {
        try {
            $response = Http::post('https://third-party-api.com/update', [
                'batches' => [$batch],
            ]);

            if ($response->successful()) {
                Log::info('Batch sent successfully', $response->json());
            } else {
                Log::error('Failed to send batch', $response->json());
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending batch: ' . $e->getMessage());
        }
    }

    private function sendIndividualToApi(User $user)
    {
        try {
            $response = Http::post('https://third-party-api.com/update-individual', [
                'email' => $user->email,
                'time_zone' => $user->timezone,
                'name' => $user->firstname . ' ' . $user->lastname,
            ]);

            if ($response->successful()) {
                Log::info('Individual request sent successfully for ' . $user->email, $response->json());
            } else {
                Log::error('Failed to send individual request for ' . $user->email, $response->json());
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending individual request for ' . $user->email . ': ' . $e->getMessage());
        }
    }

    private function logRequest($type, $count = 1)
    {
        for ($i = 0; $i < $count; $i++) {
            RequestLog::create(['type' => $type]);
        }
    }
}