<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\BankFile;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = BankTransaction::query();

        if (!auth()->user()->isAdmin()) {
            $query->whereHas('file', function($q) {
                $q->where('created_by', auth()->id());
            });
        }

        // Filters
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }
        if ($request->filled('file_id')) {
            $query->where('bank_file_id', $request->file_id);
        }
        if ($request->filled('bank_status')) {
            $query->where('bank_status', $request->bank_status);
        }
        if ($request->filled('import_status')) {
            $query->where('import_status', $request->import_status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                 $q->where('payment_ref_no', 'like', "%$search%")
                   ->orWhere('customer_ref_no', 'like', "%$search%")
                   ->orWhere('beneficiary_name', 'like', "%$search%");
            });
        }

        $transactions = $query->latest('transaction_date')->paginate(50);
        
        $files = BankFile::select('id', 'original_filename')->latest()->take(50)->get();

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
        $query = BankTransaction::query();

        if (!auth()->user()->isAdmin()) {
            $query->whereHas('file', function($q) {
                $q->where('created_by', auth()->id());
            });
        }

        // Apply filters (same as index)
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }
        if ($request->filled('file_id')) {
            $query->where('bank_file_id', $request->file_id);
        }
        if ($request->filled('bank_status')) {
            $query->where('bank_status', $request->bank_status);
        }
        if ($request->filled('import_status')) {
            $query->where('import_status', $request->import_status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                 $q->where('payment_ref_no', 'like', "%$search%")
                   ->orWhere('customer_ref_no', 'like', "%$search%")
                   ->orWhere('beneficiary_name', 'like', "%$search%");
            });
        }

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
                'IFSC', 'Payment Ref No', 'Status', 'File Source'
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
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        $bankTransaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transaction deleted successfully.');
    }
}
