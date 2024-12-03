<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TransactionRequest;

class TransactionController extends Controller
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

            $transactionId = uniqid();
            $data['created_at'] = time();
            $data['user_id'] = $request->user()->id;

            if ($data['type'] !== 'expense') {
                $orderId = $data['order_id'];
                $orderRef = $this->database->getReference('orders/' . $orderId);

                if ($data['detailed_type'] === 'dp') {
                    $orderRef->update([
                        'status/' => "processing",
                        'updated_at/' => time()
                    ]);
                } else {
                    $orderRef->update([
                        'status/' => "finished",
                        'updated_at/' => time()
                    ]);
                }
            }

            if ($data['detailed_type'] === 'operational') {
                $data['reimbursement_status'] = 'pending';
                app(UserController::class)->updateReimbursementAmount(
                    $data['user_id'],
                    $data['amount'],
                    'pending'
                );
            }

            $this->database
                ->getReference('transactions/' . $transactionId)
                ->set($data);

            app(ReportController::class)->updateMonthlyReport($data, $transactionId);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction has been created',
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
        $transactions = $this->database->getReference('transactions')->getValue();

        if (!$transactions) {
            return response()->json([
                'message' => 'No transactions found',
                'data' => []
            ]);
        }

        $users = app(UserController::class)->index();

        $formattedTransactions = [];
        foreach ($transactions as $key => $transaction) {
            $user = $users->firstWhere('id', $transaction['user_id']);

            $transaction['id'] = $key;
            $transaction['user_name'] = $user['name'];
            $transaction['date'] = isset($transaction['date']) ? $this->formatedDate($transaction['date']) : 'Invalid date';
            $transaction['amount'] = isset($transaction['amount']) ? $this->formatedAmount($transaction['amount']) : 'Rp 0,00';

            $formattedTransactions[$key] = $transaction;
        }

        return response()->json($formattedTransactions);
    }

    public function getTransactionsByMonth($month, $year = null)
    {
        if (!$year) {
            $year = date('Y');
        }

        $formattedTransactions = collect($this->index()->getData())
            ->filter(function ($transaction) use ($month, $year) {
                $transactionDate = date('n', strtotime($transaction->date));
                $transactionYear = date('Y', strtotime($transaction->date));
                return $transactionDate == $month && $transactionYear == $year;
            })
            ->toArray();

        return response()->json($formattedTransactions);
    }

    public function getTransactionsByUser($userId)
    {
        $formattedTransactions = collect($this->index()->getData())
            ->filter(function ($transaction) use ($userId) {
                return $transaction->user_id == $userId &&
                    $transaction->detailed_type == 'operational';
            })
            ->toArray();

        return response()->json($formattedTransactions);
    }

    public function getTransactionsByList(Request $request)
    {
        $transactionIds = $request->all();
        $formattedTransactions = collect($this->index()->getData())
            ->filter(function ($transaction) use ($transactionIds) {
                return in_array($transaction->id, $transactionIds); // Akses langsung properti id
            })
            ->values()
            ->toArray();

        return response()->json($formattedTransactions);
    }

    public function getTransactionsByOrder($orderId)
    {
        $formattedTransactions = $this->index()->getData();

        $transactionsByOrder = [];
        if (isset($formattedTransactions)) {
            foreach ($formattedTransactions as $transaction) {
                if ($transaction->order_id == $orderId) {
                    $transactionsByOrder[] = $transaction;
                }
            }
        }
        return $transactionsByOrder;
    }

    public function edit(Request $request)
    {
        $data = $request->all();
        $this->database->getReference('transactions' . $request['transactions_id'])
            ->update($data);

        return response()->json('Transaction has been edited');
    }

    public function updateReimbursementStatus(Request $request)
    {
        $transactionId = $request['transaction_id'];
        $newStatus = 'reimbursed';

        $currentTime = time();

        $transactionRef = $this->database->getReference('transactions/' . $transactionId);
        $transactionData = $transactionRef->getValue();

        $transactionRef->update([
            'updated_at' => $currentTime,
            'reimbursement_status' => $newStatus,
            'reimbursed_at' => $currentTime
        ]);

        $month = date('n', strtotime($transactionData['date']));
        $year = date('Y', strtotime($transactionData['date']));

        $monthlyReportRef = $this->database->getReference("monthlyReports/{$year}-{$month}/reimbursements/{$transactionData['user_id']}");
        $monthlyReportRef->update(["history/$transactionId/status" => $newStatus]);

        app(UserController::class)->updateReimbursementAmount(
            $transactionData['user_id'],
            $transactionData['amount'],
            'reimbursed'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Reimbursement status updated'
        ]);
    }

    private function formatedDate($dateString)
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
            return $dateString;
        }

        $timestamp = intval($dateString);
        if ($timestamp > 1000000000000) {
            $timestamp = intdiv($timestamp, 1000);
        }
        return date('Y-m-d', $timestamp);
    }

    private function formatedAmount($amount)
    {
        if (!is_numeric($amount)) {
            return '0';
        }
        return number_format(floatval($amount), 0, ',', '.');
    }
}
