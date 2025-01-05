<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    private $database;

    public function __construct()
    {
        $this->database = \App\Services\FirebaseService::connect();
    }

    public function getMonthlyReport($month, $year = null)
    {
        if (!$year) {
            $year = date('Y');
        }

        $params = "$year-$month";

        $report = $this->database->getReference('monthlyReports/' . $params)->getValue();

        if (!$report) {
            return response()->json([
                'message' => 'No reports found',
                'data' => []
            ]);
        }

        $transactions = app(TransactionController::class)->getTransactionsByMonth($month, $year);

        $users = app(UserController::class)->index();

        if (isset($report['reimbursements'])) {
            $reimbursements = $report['reimbursements'];
            $formattedReimbursements = [];
            foreach ($reimbursements as $key => $reimbursement) {
                $user = $users->firstWhere('id', $key);
                $formattedReimbursements[] = [
                    'id' => $key,
                    'amount' => $this->formatedAmount($reimbursement['amount']),
                    'name' => $user['name'] ?? 'Unknown',
                    'count' => $reimbursement['count'],
                    'transaction_list' => $reimbursement['transaction_list']
                ];
            }
            $report['reimbursements'] = $formattedReimbursements;
        }

        $report['totalExpense'] = $this->formatedAmount($report['totalExpense']);
        $report['totalIncome'] = $this->formatedAmount($report['totalIncome']);
        $report['totalPackaging'] = $this->formatedAmount($report['totalPackaging']);
        $report['totalLegal'] = $this->formatedAmount($report['totalLegal']);
        $report['totalTransport'] = $this->formatedAmount($report['totalTransport']);
        $report['totalProduction'] = $this->formatedAmount($report['totalProduction']);

        return response()->json([
            'message' => 'Monthly report retrieved successfully',
            'data' => $report,
            'transactions' => $transactions->original,
            'month' => $month,
            'year' => $year
        ]);
    }


    public function updateMonthlyReport(array $data, $transactionId)
    {
        $month = str_pad(date('n', strtotime($data['date'])), 2, '0', STR_PAD_LEFT);
        $year = date('Y', strtotime($data['date']));

        $reportRef = $this->database->getReference("monthlyReports/{$year}-{$month}");

        $currentData = $reportRef->getValue();

        if ($currentData === null) {
            $reportData = [
                'incomeCount' => 0,
                'expenseCount' => 0,
                'totalIncome' => 0,
                'totalExpense' => 0,
                'totalTransport' => 0,
                'totalLegal' => 0,
                'totalPackaging' => 0,
                'totalProduction' => 0,
                'percentage' => [
                    'incomePercentage' => 0,
                    'transportPercentage' => 0,
                    'legalPercentage' => 0,
                    'packagingPercentage' => 0,
                ],
                'reimbursements' => []
            ];
        } else {
            $reportData = $currentData;
        }

        if ($data['type'] === 'income') {
            $reportData['totalIncome'] += floatval($data['amount']);
            $reportData['incomeCount']++;
        } else {
            $reportData['totalExpense'] += floatval($data['amount']);
            $reportData['expenseCount']++;

            if ($data['detailed_type'] === 'operational') {

                $userId = $data['user_id'];

                if (isset($reportData['reimbursements'][$userId])) {
                    $reportData['reimbursements'][$userId]['amount'] += floatval($data['amount']);
                    $reportData['reimbursements'][$userId]['count']++;
                } else {
                    $reportData['reimbursements'][$userId] = [
                        'amount' => floatval($data['amount']),
                        'count' => 1,
                    ];
                }

                $reportData['reimbursements'][$userId]['transaction_list'] = [$transactionId];

                switch ($data['category']) {
                    case 'transport':
                        $reportData['totalTransport'] += floatval($data['amount']);
                        break;
                    case 'legal':
                        $reportData['totalLegal'] += floatval($data['amount']);
                        break;
                    case 'packaging':
                        $reportData['totalPackaging'] += floatval($data['amount']);
                        break;
                }
            } else {
                $reportData['totalProduction'] += floatval($data['amount']);
            }
        }

        $totalAmount = $reportData['totalIncome'] + $reportData['totalExpense'];

        if ($totalAmount > 0) {
            $reportData['percentage'] = [
                'incomePercentage' => round(($reportData['totalIncome'] / $totalAmount) * 100, 2),
                'transportPercentage' => round(($reportData['totalTransport'] / $totalAmount) * 100, 2),
                'legalPercentage' => round(($reportData['totalLegal'] / $totalAmount) * 100, 2),
                'packagingPercentage' => round(($reportData['totalPackaging'] / $totalAmount) * 100, 2),
                'productionPercentage' => round(($reportData['totalProduction'] / $totalAmount) * 100, 2),
            ];
        }

        $reportRef->set($reportData);

        return $reportData;
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
