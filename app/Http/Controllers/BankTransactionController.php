<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\BankFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = BankTransaction::query()
            ->with('file:id,bank_type,original_filename,created_by');

        $this->scopeVisibleToUser($query);
        $this->applyFilters($query, $request);

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(50);

        $files = BankFile::select('id', 'original_filename')
            ->when(! auth()->user()->isAdmin(), fn ($query) => $query->where('created_by', auth()->id()))
            ->latest()
            ->take(100)
            ->get();

        return view('transactions.index', compact('transactions', 'files'));
    }

    public function show(BankTransaction $bankTransaction)
    {
        if (!auth()->user()->isAdmin() && $bankTransaction->file->created_by !== auth()->id()) {
            abort(403);
        }
        return view('transactions.show', compact('bankTransaction'));
    }

    public function export(Request $request)
    {
        $query = BankTransaction::query()
            ->with('file:id,bank_type,original_filename,created_by');

        $this->scopeVisibleToUser($query);
        $this->applyFilters($query, $request);

        $query->orderByDesc('transaction_date')->orderByDesc('id');

        $filename = 'Export_Transactions_' . date('Ymd_His') . '.csv';
        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Expires'             => '0',
            'Pragma'              => 'public',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Bank Type', 'Transaction Date', 'Amount', 'Transaction Type',
                'Debit Account', 'Beneficiary Account', 'Beneficiary Name',
                'IFSC', 'Payment Ref No', 'Customer Ref No', 'Transaction ID', 'Invoice ID', 'UTR/Bank Remarks', 'Status', 'File Source'
            ]);

            $query->chunk(1000, function ($rows) use ($handle) {
                foreach ($rows as $t) {
                    fputcsv($handle, [
                        $t->id,
                        $t->file->bank_type ?? 'Unknown',
                        $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '',
                        $t->amount,
                        $t->transaction_type,
                        $t->debit_account_no,
                        $t->beneficiary_account_no,
                        $t->beneficiary_name,
                        $t->ifsc,
                        $t->payment_ref_no,
                        $t->customer_ref_no,
                        $t->transaction_id,
                        $t->invoice_id,
                        $t->utr_bank_remarks,
                        $t->bank_status,
                        $t->file->original_filename ?? ''
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function destroy(BankTransaction $bankTransaction)
    {
        if (!auth()->user()->can('delete-transactions')) {
            abort(403);
        }

        $bankTransaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transaction deleted successfully.');
    }

    private function scopeVisibleToUser(Builder $query): void
    {
        if (! auth()->user()->isAdmin()) {
            $query->whereHas('file', function ($fileQuery) {
                $fileQuery->where('created_by', auth()->id());
            });
        }
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('file_id')) {
            $query->where('bank_file_id', $request->file_id);
        }

        if ($request->filled('bank_type')) {
            $query->whereHas('file', fn ($fileQuery) => $fileQuery->where('bank_type', $request->bank_type));
        }

        if ($request->filled('bank_status')) {
            $query->where('bank_status', $request->bank_status);
        }

        if ($request->filled('import_status')) {
            $query->where('import_status', $request->import_status);
        }

        if ($request->filled('transaction_id')) {
            $query->where('transaction_id', 'like', '%' . $request->transaction_id . '%');
        }

        if ($request->filled('vendor_name')) {
            $query->where('beneficiary_name', 'like', '%' . $request->vendor_name . '%');
        }

        if ($request->filled('account_no')) {
            $account = $request->account_no;
            $query->where(function ($accountQuery) use ($account) {
                $accountQuery->where('beneficiary_account_no', 'like', "%{$account}%")
                    ->orWhere('debit_account_no', 'like', "%{$account}%");
            });
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->amount_max);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('payment_ref_no', 'like', "%{$search}%")
                    ->orWhere('customer_ref_no', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%")
                    ->orWhere('beneficiary_name', 'like', "%{$search}%")
                    ->orWhere('beneficiary_account_no', 'like', "%{$search}%")
                    ->orWhere('debit_account_no', 'like', "%{$search}%")
                    ->orWhere('ifsc', 'like', "%{$search}%")
                    ->orWhere('invoice_id', 'like', "%{$search}%")
                    ->orWhere('token_id', 'like', "%{$search}%")
                    ->orWhere('email_id', 'like', "%{$search}%")
                    ->orWhere('utr_bank_remarks', 'like', "%{$search}%")
                    ->orWhere('remarks_for_client', 'like', "%{$search}%")
                    ->orWhere('remarks_for_beneficiary', 'like', "%{$search}%");
            });
        }
    }
}
