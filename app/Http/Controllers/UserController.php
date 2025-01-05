<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private $database;

    public function __construct()
    {
        $this->database = \App\Services\FirebaseService::connect();
    }

    public function index()
    {
        $users = collect($this->database->getReference('users')->getValue())->map(function ($userData, $key) {
            $userData['id'] = $key;
            return $userData;
        })->values();

        return $users;
    }

    public function getData(Request $request)
    {
        $userData = $request->user();

        if (!isset($userData->reimbursement_data)) {
            $userData->reimbursement_data = [
                'total_pending_amount' => 0,
                'total_reimbursed_amount' => 0,
            ];
        }

        $userData->reimbursement_data['total_pending_amount']  = $this->formatedAmount($userData->reimbursement_data['total_pending_amount']);
        $userData->reimbursement_data['total_reimbursed_amount'] = $this->formatedAmount($userData->reimbursement_data['total_reimbursed_amount']);
        
        $transactionByUser = app(TransactionController::class)->getTransactionsByUser($userData->id);
        return response()->json([
            'data' =>  $userData,
            'transactions' => $transactionByUser->original
        ]);
    }

    public function updateReimbursementAmount($userId, $amount, $status)
    {
        try {
            $userRef = $this->database->getReference('users/' . $userId);
            $userData = $userRef->getValue();

            if (!isset($userData['reimbursement_data'])) {
                $userData['reimbursement_data'] = [
                    'total_pending_amount' => 0,
                    'total_reimbursed_amount' => 0,
                ];
            }

            if ($status === 'pending') {
                $userData['reimbursement_data']['total_pending_amount'] += $amount;
            } else if ($status === 'reimbursed') {
                $userData['reimbursement_data']['total_pending_amount'] -= $amount;
                $userData['reimbursement_data']['total_reimbursed_amount'] += $amount;
            }

            $userRef->update($userData);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        try {

            $data = $request->all();

            $userId = uniqid();
            $hashedPassword = Hash::make($request->password);

            $data['password'] = $hashedPassword;
            $data['created_at'] = time();

            $this->database
                ->getReference('users/' . $userId)
                ->set($data);

            return response()->json([
                'status' => 'success',
                'message' => 'User has been created',
                'data' => [
                    'userId' => $userId
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $this->database
            ->getReference('users' . $request['user_id'])
            ->remove();

        return response()->json('User has been deleted');
    }

    public function edit(Request $request)
    {
        $data = $request->all();
        $this->database->getReference('users' . $request['users_id'])
            ->update($data);

        return response()->json('User has been edited');
    }

    private function formatedAmount($amount)
    {
        if (!is_numeric($amount)) {
            return '0'; 
        }
        return number_format(floatval($amount), 0, ',', '.');
    }
}