<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderController extends Controller
{
    private $database;

    public function __construct()
    {
        $this->database = \App\Services\FirebaseService::connect();
    }

    public function create(Request $request)
    {
        try {
            $data = $request->all();

            $orderId = uniqid();

            $priceAgreement = 0;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as &$item) {
                    if (isset($item['items_price'])) {
                        $priceAgreement += $item['items_price'];
                    }
                }
            }

            $data['price_agreement'] = $priceAgreement;

            $data['status'] = 'pending';
            $data['created_at'] = time();

            $this->database
                ->getReference('orders/' . $orderId)
                ->set($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Order has been created',
                'id' => $orderId,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $orders = $this->database->getReference('orders')->getValue();

        if (!$orders) {
            return response()->json([
                'message' => 'No orders found',
                'data' => []
            ]);
        }

        $formattedOrders = [];
        foreach ($orders as $key => $order) {
            $order['id'] = $key;
            $order['created_at'] = $this->formatedDate($order['created_at']);
            $order['price_agreement'] = $this->formatedAmount($order['price_agreement']);
            $order['completion_date'] = $this->formatedDate($order['completion_date']);

            foreach ($order['items'] as &$item) {
                $item['items_price'] = $this->formatedAmount($item['items_price']);
            }
            $formattedOrders[] = $order;
        }

        return response()->json([
            'data' => $formattedOrders,
            'message' => 'Orders retrieved successfully',
        ], 200);
    }

    public function show($id)
    {
        $formattedOrders = $this->index()->getData(true)['data'];
        $formattedOrder = collect($formattedOrders)->firstWhere('id', $id);

        if (!$formattedOrder) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $transactions = app(TransactionController::class)->getTransactionsByOrder($id);

        return response()->json([
            'message' => 'Order retrieved successfully',
            'data' => $formattedOrder,
            'transactions' => $transactions->original
        ]);
    }

    public function getOnGoingOrders()
    {
        $response = $this->index();
        $orders = collect($response->getData(true)['data']);

        if ($orders->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => 'No orders found',
            ], 200);
        }

        $ongoingOrders = $orders->filter(function ($order) {
            return isset($order['status']) && $order['status'] !== 'finished';
        })->values();

        if ($ongoingOrders->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => 'No ongoing orders found',
            ], 200);
        }

        return response()->json([
            'data' => $ongoingOrders,
            'message' => 'Ongoing orders retrieved successfully',
        ], 200);
    }

    public function edit(Request $request)
    {
        $data = $request->all();
        $this->database->getReference('orders' . $request['order_id'])
            ->update($data);

        return response()->json('Order has been edited');
    }

    public function deleteFromList(Request $request)
    {
        $date = time();

        $this->database
            ->getReference('orders' . $request['orderId'])
            ->update([
                'deleted_at' => $date,
            ]);

        return response()->json('Order has been deleted');
    }

    public function formatedDate($props)
    {
        return Carbon::createFromTimestamp($props)->translatedFormat('d F Y');
    }

    private function formatedAmount($amount)
    {
        if (!is_numeric($amount)) {
            return '0';
        }
        return number_format(floatval($amount), 0, ',', '.');
    }
}
