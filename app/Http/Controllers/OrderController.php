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

            $formattedOrders[$key] = $order;
        }

        return response()->json($formattedOrders);
    }

    public function show($id)
    {
        $formattedOrders = $this->index()->getData();
        $formattedOrder = collect($formattedOrders)->firstWhere('id', $id);

        $transactions = app(TransactionController::class)->getTransactionsByOrder($id);

        $formattedOrder->transactions = $transactions;

        return response()->json($formattedOrder);
    }

    public function getOnGoingOrders()
    {
        $orders = collect($this->index()->getData())
            ->filter(function ($order) {
                return $order->status !== 'finished';
            })
            ->toArray();

        return response()->json($orders);
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
