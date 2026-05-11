<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BankTransactionsExport implements FromCollection, WithHeadings, WithStyles
{
    protected $transactions;

    public function __construct($transactions)
    {
        $this->transactions = $transactions;
    }

    public function collection()
    {
        return $this->transactions->map(function ($transaction) {
            return [
                'ID' => $transaction->id,
                'Transaction Type' => $transaction->transaction_type,
                'Amount' => $transaction->amount,
                'Debit Account' => $transaction->debit_account_no,
                'IFSC' => $transaction->ifsc,
                'Beneficiary Account' => $transaction->beneficiary_account_no,
                'Beneficiary Name' => $transaction->beneficiary_name,
                'Transaction ID' => $transaction->transaction_id,
                'Transaction Date' => $transaction->transaction_date,
                'Invoice ID' => $transaction->invoice_id,
                'Status' => $transaction->bank_status ?? 'PENDING',
                'Import Status' => $transaction->import_status,
                'Payment Ref' => $transaction->payment_ref_no,
                'Email' => $transaction->email_id,
                'Phone' => $transaction->phone,
                'Remarks Client' => $transaction->remarks_for_client,
                'Remarks Beneficiary' => $transaction->remarks_for_beneficiary,
                'Created' => $transaction->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Transaction Type',
            'Amount',
            'Debit Account',
            'IFSC',
            'Beneficiary Account',
            'Beneficiary Name',
            'Transaction ID',
            'Transaction Date',
            'Invoice ID',
            'Status',
            'Import Status',
            'Payment Ref',
            'Email',
            'Phone',
            'Remarks Client',
            'Remarks Beneficiary',
            'Created',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
            ],
        ];
    }
}
