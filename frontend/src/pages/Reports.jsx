import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';

export default function Reports() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  // Date range filters for standard reports
  const [startDate, setStartDate] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
  });
  const [endDate, setEndDate] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
  });

  // Filter for Month Comparison (Task 13.4)
  const [selectedMonth, setSelectedMonth] = useState(() => new Date().toISOString().slice(0, 7));

  // Filter for Savings Goal Report (Task 13.5)
  const [savingsStatus, setSavingsStatus] = useState('all');

  // State for data
  const [summary, setSummary] = useState(null);
  const [incomeVsExpense, setIncomeVsExpense] = useState([]);
  const [accountBalances, setAccountBalances] = useState([]);
  const [monthComparison, setMonthComparison] = useState(null);
  const [savingsReport, setSavingsReport] = useState(null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const requestIdRef = useRef(0);

  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  };

  const fetchReports = (householdId) => {
    if (!householdId) return;
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);

    Promise.all([
      api.get(`/households/${householdId}/reports/summary?start_date=${startDate}&end_date=${endDate}`),
      api.get(`/households/${householdId}/reports/income-vs-expense?start_date=${startDate}&end_date=${endDate}`),
      api.get(`/households/${householdId}/reports/account-balances`),
      api.get(`/households/${householdId}/reports/month-comparison?month=${selectedMonth}`),
      api.get(`/households/${householdId}/reports/savings?status=${savingsStatus}`),
    ])
      .then(([summaryRes, trendRes, accountsRes, compareRes, savingsRes]) => {
        if (requestIdRef.current !== currentRequestId) return;
        setSummary(summaryRes.data.summary ?? null);
        setIncomeVsExpense(trendRes.data.data ?? []);
        setAccountBalances(accountsRes.data.accounts ?? []);
        setMonthComparison(compareRes.data ?? null);
        setSavingsReport(savingsRes.data ?? null);
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Gagal memuat data laporan';
        setError(msg);
      })
      .finally(() => {
        if (requestIdRef.current !== currentRequestId) return;
        setLoading(false);
      });
  };

  useEffect(() => {
    if (contextLoading) return;
    if (!activeHouseholdId) return;
    fetchReports(activeHouseholdId);
  }, [activeHouseholdId, contextLoading, startDate, endDate, selectedMonth, savingsStatus]);

  if (contextLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600">Memuat data household…</p>
      </div>
    );
  }

  if (!activeHouseholdId) {
    return <Alert type="info">Belum ada household aktif. Pilih household pada selector di atas.</Alert>;
  }

  return (
    <div className="flex min-h-screen justify-center bg-gray-100 p-4">
      <Card className="max-w-6xl w-full space-y-6 p-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row justify-between md:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-indigo-600">Laporan Keuangan &amp; Analitik</h1>
            <p className="text-gray-600 text-sm">Ringkasan arus kas, saldo akun, perbandingan bulan, dan progres tabungan</p>
          </div>

          {/* Date Filter Controls & Export Buttons */}
          <div className="flex flex-wrap items-center gap-2 bg-white p-2 rounded-lg border shadow-sm text-sm">
            <span className="text-gray-500 font-medium text-xs">Periode:</span>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="border border-gray-300 rounded px-2 py-1 text-xs"
            />
            <span className="text-gray-400 text-xs">s/d</span>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="border border-gray-300 rounded px-2 py-1 text-xs"
            />
            <a
              href={`/api/v1/households/${activeHouseholdId}/export/transactions?format=csv&start_date=${startDate}&end_date=${endDate}`}
              download
              className="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-semibold"
            >
              Export CSV
            </a>
            <a
              href={`/api/v1/households/${activeHouseholdId}/export/transactions?format=json&start_date=${startDate}&end_date=${endDate}`}
              target="_blank"
              rel="noreferrer"
              className="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold"
            >
              Export JSON
            </a>
          </div>
        </div>

        {error && <Alert type="error">{error}</Alert>}

        {loading ? (
          <div className="py-12 text-center text-gray-500 font-medium">Memuat data laporan…</div>
        ) : (
          <>
            {/* Summary Cards */}
            {summary && (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-green-700 uppercase tracking-wider">Total Pemasukan</span>
                  <div className="text-2xl font-bold text-green-800 mt-1">{formatRupiah(summary.total_income)}</div>
                  <span className="text-xs text-green-600 mt-1 block">Periode ini</span>
                </div>

                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-red-700 uppercase tracking-wider">Total Pengeluaran</span>
                  <div className="text-2xl font-bold text-red-800 mt-1">{formatRupiah(summary.total_expense)}</div>
                  <span className="text-xs text-red-600 mt-1 block">Periode ini</span>
                </div>

                <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-indigo-700 uppercase tracking-wider">Arus Kas Bersih</span>
                  <div className={`text-2xl font-bold mt-1 ${Number(summary.net_cashflow) >= 0 ? 'text-indigo-900' : 'text-red-700'}`}>
                    {formatRupiah(summary.net_cashflow)}
                  </div>
                  <span className="text-xs text-indigo-600 mt-1 block">Pemasukan - Pengeluaran</span>
                </div>

                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-blue-700 uppercase tracking-wider">Total Saldo Kas/Bank</span>
                  <div className="text-2xl font-bold text-blue-900 mt-1">{formatRupiah(summary.total_account_balance)}</div>
                  <span className="text-xs text-blue-600 mt-1 block">Seluruh Akun Aktif</span>
                </div>
              </div>
            )}

            {/* Task 13.4 — Month Comparison Section */}
            {monthComparison && (
              <div className="bg-white border rounded-lg p-5 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-3">
                  <div>
                    <h2 className="text-lg font-bold text-gray-800">Perbandingan Bulan (Month Comparison)</h2>
                    <p className="text-xs text-gray-500">
                      Membandingkan performa keuangan bulan {monthComparison.selected_month} dengan bulan sebelumnya ({monthComparison.previous_month})
                    </p>
                  </div>
                  <div className="flex items-center space-x-2">
                    <span className="text-xs font-medium text-gray-500">Pilih Bulan:</span>
                    <input
                      type="month"
                      value={selectedMonth}
                      onChange={(e) => setSelectedMonth(e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-xs"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                  {/* Income Comparison */}
                  <div className="border rounded-lg p-4 bg-gray-50 space-y-2">
                    <span className="text-xs font-semibold text-gray-500 uppercase">Pemasukan</span>
                    <div className="flex justify-between items-baseline">
                      <span className="text-sm font-bold text-gray-900">{formatRupiah(monthComparison.current_month?.income)}</span>
                      <span className="text-xs text-gray-400">vs {formatRupiah(monthComparison.previous_month_data?.income)}</span>
                    </div>
                    <div className="flex items-center justify-between text-xs pt-1 border-t">
                      <span className="text-gray-500">Perubahan:</span>
                      <span className={`font-bold ${Number(monthComparison.comparison?.income_diff) >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                        {Number(monthComparison.comparison?.income_diff) >= 0 ? '+' : ''}{formatRupiah(monthComparison.comparison?.income_diff)} ({monthComparison.comparison?.income_change_percentage}%)
                      </span>
                    </div>
                  </div>

                  {/* Expense Comparison */}
                  <div className="border rounded-lg p-4 bg-gray-50 space-y-2">
                    <span className="text-xs font-semibold text-gray-500 uppercase">Pengeluaran</span>
                    <div className="flex justify-between items-baseline">
                      <span className="text-sm font-bold text-gray-900">{formatRupiah(monthComparison.current_month?.expense)}</span>
                      <span className="text-xs text-gray-400">vs {formatRupiah(monthComparison.previous_month_data?.expense)}</span>
                    </div>
                    <div className="flex items-center justify-between text-xs pt-1 border-t">
                      <span className="text-gray-500">Perubahan:</span>
                      <span className={`font-bold ${Number(monthComparison.comparison?.expense_diff) <= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                        {Number(monthComparison.comparison?.expense_diff) >= 0 ? '+' : ''}{formatRupiah(monthComparison.comparison?.expense_diff)} ({monthComparison.comparison?.expense_change_percentage}%)
                      </span>
                    </div>
                  </div>

                  {/* Net Cashflow Comparison */}
                  <div className="border rounded-lg p-4 bg-gray-50 space-y-2">
                    <span className="text-xs font-semibold text-gray-500 uppercase">Kas Bersih (Net)</span>
                    <div className="flex justify-between items-baseline">
                      <span className="text-sm font-bold text-gray-900">{formatRupiah(monthComparison.current_month?.net)}</span>
                      <span className="text-xs text-gray-400">vs {formatRupiah(monthComparison.previous_month_data?.net)}</span>
                    </div>
                    <div className="flex items-center justify-between text-xs pt-1 border-t">
                      <span className="text-gray-500">Perubahan:</span>
                      <span className={`font-bold ${Number(monthComparison.comparison?.net_diff) >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                        {Number(monthComparison.comparison?.net_diff) >= 0 ? '+' : ''}{formatRupiah(monthComparison.comparison?.net_diff)} ({monthComparison.comparison?.net_change_percentage}%)
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Task 13.5 — Savings Goals Report Section */}
            {savingsReport && (
              <div className="bg-white border rounded-lg p-5 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-3">
                  <div>
                    <h2 className="text-lg font-bold text-gray-800">Laporan Progres Tabungan (Savings Goals Report)</h2>
                    <p className="text-xs text-gray-500">Progres ketercapaian seluruh target keuangan keluarga</p>
                  </div>
                  <div className="flex items-center space-x-2">
                    <span className="text-xs font-medium text-gray-500">Status:</span>
                    <select
                      value={savingsStatus}
                      onChange={(e) => setSavingsStatus(e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-xs"
                    >
                      <option value="all">Semua Status</option>
                      <option value="active">Aktif</option>
                      <option value="completed">Selesai</option>
                    </select>
                  </div>
                </div>

                {/* Overall Savings Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 bg-indigo-50/60 p-4 rounded-lg text-xs">
                  <div>
                    <span className="text-gray-500 font-medium">Total Goal Tabungan</span>
                    <p className="text-lg font-bold text-gray-900 mt-0.5">
                      {savingsReport.summary?.total_goals_count} ({savingsReport.summary?.completed_goals_count} selesai)
                    </p>
                  </div>
                  <div>
                    <span className="text-gray-500 font-medium">Total Target Keseluruhan</span>
                    <p className="text-lg font-bold text-indigo-900 mt-0.5">{formatRupiah(savingsReport.summary?.total_target_amount)}</p>
                  </div>
                  <div>
                    <span className="text-gray-500 font-medium">Total Terkumpul</span>
                    <p className="text-lg font-bold text-emerald-700 mt-0.5">{formatRupiah(savingsReport.summary?.total_current_amount)}</p>
                  </div>
                  <div>
                    <span className="text-gray-500 font-medium">Overall Progres</span>
                    <p className="text-lg font-bold text-indigo-700 mt-0.5">{savingsReport.summary?.overall_progress_percentage}%</p>
                  </div>
                </div>

                {/* Goals List */}
                {savingsReport.goals?.length === 0 ? (
                  <p className="text-sm text-gray-500 italic py-2">Belum ada target tabungan yang terdaftar.</p>
                ) : (
                  <div className="space-y-3">
                    {savingsReport.goals?.map((goal) => (
                      <div key={goal.id} className="border rounded-lg p-3 bg-white hover:bg-gray-50/80 transition space-y-2">
                        <div className="flex justify-between items-center text-sm">
                          <span className="font-bold text-gray-900">{goal.name}</span>
                          <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${goal.is_completed ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800'}`}>
                            {goal.is_completed ? 'Selesai' : `${goal.progress_percentage}%`}
                          </span>
                        </div>
                        <div className="w-full bg-gray-200 h-2 rounded-full overflow-hidden">
                          <div
                            className={`h-full transition-all duration-300 ${goal.is_completed ? 'bg-emerald-500' : 'bg-indigo-600'}`}
                            style={{ width: `${Math.min(goal.progress_percentage, 100)}%` }}
                          />
                        </div>
                        <div className="flex justify-between items-center text-xs text-gray-500">
                          <span>Terkumpul: <strong className="text-gray-800">{formatRupiah(goal.current_amount)}</strong></span>
                          <span>Sisa Target: <strong className="text-gray-800">{formatRupiah(goal.remaining_amount)}</strong></span>
                          <span>Target Total: <strong className="text-gray-800">{formatRupiah(goal.target_amount)}</strong></span>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Income vs Expense Table */}
            <div className="bg-white border rounded-lg p-5 space-y-3">
              <h2 className="text-lg font-bold text-gray-800">Tren Pemasukan vs Pengeluaran Harian</h2>
              {incomeVsExpense.length === 0 ? (
                <p className="text-sm text-gray-500 italic">Belum ada transaksi pada periode yang dipilih.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50 text-gray-700 font-semibold">
                      <tr>
                        <th className="px-4 py-2 text-left">Tanggal</th>
                        <th className="px-4 py-2 text-right">Pemasukan</th>
                        <th className="px-4 py-2 text-right">Pengeluaran</th>
                        <th className="px-4 py-2 text-right">Selisih Bersih</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {incomeVsExpense.map((row) => (
                        <tr key={row.date} className="hover:bg-gray-50">
                          <td className="px-4 py-2 font-medium text-gray-900">{row.date}</td>
                          <td className="px-4 py-2 text-right text-green-700 font-medium">{formatRupiah(row.income)}</td>
                          <td className="px-4 py-2 text-right text-red-700 font-medium">{formatRupiah(row.expense)}</td>
                          <td className={`px-4 py-2 text-right font-bold ${Number(row.net) >= 0 ? 'text-gray-900' : 'text-red-600'}`}>
                            {formatRupiah(row.net)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {/* Account Balances Summary */}
            <div className="bg-white border rounded-lg p-5 space-y-3">
              <h2 className="text-lg font-bold text-gray-800">Ringkasan Saldo Akun Keuangan</h2>
              {accountBalances.length === 0 ? (
                <p className="text-sm text-gray-500 italic">Belum ada akun terdaftar.</p>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                  {accountBalances.map((acc) => (
                    <div key={acc.id} className="border rounded-lg p-4 bg-gray-50 space-y-1">
                      <div className="flex justify-between items-center">
                        <span className="font-bold text-gray-900">{acc.name}</span>
                        <span className="text-xs uppercase bg-gray-200 text-gray-700 px-2 py-0.5 rounded font-mono">{acc.type}</span>
                      </div>
                      <div className="text-xs text-gray-500">Saldo Awal: {formatRupiah(acc.initial_balance)}</div>
                      <div className="text-lg font-bold text-indigo-700 pt-1">
                        Saldo Akhir: {formatRupiah(acc.current_balance)}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}
      </Card>
    </div>
  );
}
