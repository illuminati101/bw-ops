<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request, PaymentService $payments)
    {
        return [
            'methods' => $request->user()->paymentMethods()->orderByDesc('is_default')->get()->map(fn ($m) => [
                'id' => $m->id, 'label' => $m->label(), 'brand' => $m->brand, 'last4' => $m->last4, 'is_default' => $m->is_default, 'expired' => $m->isExpired(), 'gateway' => $m->gateway,
            ]),
            'record_only' => $payments->isRecordOnly(),
        ];
    }

    public function setup(Request $request, PaymentService $payments)
    {
        $return = $request->input('return_url', url('/payments/return'));
        $result = $payments->beginCardSetup($request->user(), url('/payments/callback').'?return='.urlencode($return));

        return [
            'redirect_url' => $result['redirect_url'],
            'reference' => $result['reference'],
            'method_id' => $result['method']?->id,
            'record_only' => $payments->isRecordOnly(),
        ];
    }

    public function complete(Request $request, PaymentService $payments)
    {
        $data = $request->validate(['reference' => 'required|string|max:64']);
        $method = $payments->completeCardSetup($request->user(), $data['reference']);

        return ['method' => ['id' => $method->id, 'label' => $method->label()]];
    }

    public function makeDefault(Request $request, int $id)
    {
        $user = $request->user();
        $method = $user->paymentMethods()->findOrFail($id);
        $user->paymentMethods()->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return response()->noContent();
    }

    public function destroy(Request $request, int $id)
    {
        $method = $request->user()->paymentMethods()->findOrFail($id);
        $inUse = \App\Models\Booking::query()->where('payment_method_id', $method->id)->open()->exists();
        abort_if($inUse, 422, 'This card is linked to an upcoming booking. Add another card first, or cancel the booking.');
        $method->delete();

        return response()->noContent();
    }
}
