import React, { useEffect, useState, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';

export default function Home() {
  const { activeHouseholdId, activeHousehold, loading: contextLoading } = useHousehold();

  const [summary, setSummary] = useState(null);
  const [budgets, setBudgets] = useState([]);
  const [savings, setSavings] = useState([]);
  const [upcomingBills, setUpcomingBills] = useState([]);
  const [transactions, setTransactions] = useState([]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const requestIdRef = useRef(0);

  const formatRupiah = (value) => {
    const number = Number(value);
    if (Number.isNaN(number)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
  };

  const fetchDashboard = (householdId) => {
    if (!householdId) return;
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    setLoading(true);
    setError(null);

    Promise.all([
      api.get(`/households/${householdId}/reports/summary`),
      api.get(`/households/${householdId}/budgets/alerts`),
      api.get(`/households/${householdId}/savings`),
      api.get(`/households/${householdId}/recurring-transactions/upcoming-bills`),
      api.get(`/households/${householdId}/transactions`),
    ])
      .then(([summaryRes, budgetsRes, savingsRes, billsRes, txRes]) => {
        if (requestIdRef.current !== currentRequestId) return;
        setSummary(summaryRes.data.summary ?? null);
        setBudgets(budgetsRes.data.alerts ?? budgetsRes.data.budgets ?? []);
        setSavings(savingsRes.data.savings ?? savingsRes.data.data ?? []);
        setUpcomingBills(billsRes.data.upcoming_bills ?? billsRes.data.data ?? []);
        setTransactions((txRes.data.transactions ?? txRes.data.data ?? []).slice(0, 5));
      })
      .catch((err) => {
        if (requestIdRef.current !== currentRequestId) return;
        const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Gagal memuat ringkasan dashboard';
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
    fetchDashboard(activeHouseholdId);
  }, [activeHouseholdId, contextLoading]);

  if (contextLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
        <p className="text-gray-600 font-medium">Memuat data household...</p>
      </div>
    );
  }

  if (!activeHouseholdId) {
    return (
      <div className="max-w-6xl mx-auto p-4">
        <Alert type="info">Belum ada household aktif. Silakan pilih atau buat household terlebih dahulu.</Alert>
      </div>
    );
  }

  return (
    <div className="flex justify-center bg-gray-100 p-4">
      <Card className="max-w-6xl w-full space-y-6 p-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row justify-between md:items-center gap-4 border-b pb-4">
          <div>
            <h1 className="text-2xl font-bold text-indigo-600">Dashboard Keuangan Keluarga</h1>
            <p className="text-sm text-gray-500 mt-1">
              Ringkasan aktivitas keuangan untuk <span className="font-semibold text-gray-800">{activeHousehold?.name || 'Household Aktif'}</span>
            </p>
          </div>
          <div className="flex space-x-2">
            <Link to="/transactions" className="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm font-semibold transition">
              + Catat Transaksi
            </Link>
          </div>
        </div>

        {error && <Alert type="error">{error}</Alert>}

        {loading ? (
          <div className="py-16 text-center text-gray-500 font-medium">Memuat data dashboard...</div>
        ) : (
          <div className="space-y-6">
            {/* 1. 4 KPI Cards */}
            {summary && (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-blue-700 uppercase tracking-wider">Total Saldo</span>
                  <div className="text-2xl font-bold text-blue-900 mt-1">{formatRupiah(summary.total_account_balance)}</div>
                  <span className="text-xs text-blue-600 mt-1 block">Seluruh Akun Aktif</span>
                </div>

                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-green-700 uppercase tracking-wider">Pemasukan Bulan Ini</span>
                  <div className="text-2xl font-bold text-green-800 mt-1">{formatRupiah(summary.total_income)}</div>
                  <span className="text-xs text-green-600 mt-1 block">Total Income</span>
                </div>

                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-red-700 uppercase tracking-wider">Pengeluaran Bulan Ini</span>
                  <div className="text-2xl font-bold text-red-800 mt-1">{formatRupiah(summary.total_expense)}</div>
                  <span className="text-xs text-red-600 mt-1 block">Total Expense</span>
                </div>

                <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                  <span className="text-xs font-semibold text-indigo-700 uppercase tracking-wider">Arus Kas Bersih</span>
                  <div className={`text-2xl font-bold mt-1 ${Number(summary.net_cashflow) >= 0 ? 'text-indigo-900' : 'text-red-700'}`}>
                    {formatRupiah(summary.net_cashflow)}
                  </div>
                  <span className="text-xs text-indigo-600 mt-1 block">Net Cashflow</span>
                </div>
              </div>
            )}

            {/* 2 & 3. Grid Row: Budgets & Financial Goals */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Budget Alerts / Progress */}
              <div className="bg-white border rounded-lg p-5 space-y-4">
                <div className="flex justify-between items-center border-b pb-3">
                  <h2 className="text-lg font-bold text-gray-800">Status &amp; Anggaran (Budgets)</h2>
                  <Link to="/budgets" className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Lihat Semua &rarr;</Link>
                </div>
                {budgets.length === 0 ? (
                  <p className="text-xs text-gray-500 py-4 text-center">Belum ada anggaran yang diatur untuk bulan ini.</p>
                ) : (
                  <div className="space-y-3">
                    {budgets.slice(0, 4).map((b) => {
                      const limit = Number(b.amount || b.monthly_limit || 0);
                      const spent = Number(b.spent || b.current_spent || 0);
                      const pct = limit > 0 ? Math.min(Math.round((spent / limit) * 100), 100) : 0;
                      const isOver = spent > limit;
                      return (
                        <div key={b.id || b.category_id} className="p-3 bg-gray-50 rounded border text-xs space-y-1">
                          <div className="flex justify-between font-semibold text-gray-800">
                            <span>{b.category_name || b.name || 'Anggaran'}</span>
                            <span className={isOver ? 'text-red-600 font-bold' : 'text-gray-700'}>
                              {formatRupiah(spent)} / {formatRupiah(limit)} ({pct}%)
                            </span>
                          </div>
                          <div className="w-full bg-gray-200 rounded-full h-2">
                            <div
                              className={`h-2 rounded-full ${isOver ? 'bg-red-600' : pct >= 80 ? 'bg-amber-500' : 'bg-indigo-600'}`}
                              style={{ width: `${pct}%` }}
                            ></div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* Savings & Financial Goals */}
              <div className="bg-white border rounded-lg p-5 space-y-4">
                <div className="flex justify-between items-center border-b pb-3">
                  <h2 className="text-lg font-bold text-gray-800">Target Tabungan (Financial Goals)</h2>
                  <Link to="/savings" className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Lihat Semua &rarr;</Link>
                </div>
                {savings.length === 0 ? (
                  <p className="text-xs text-gray-500 py-4 text-center">Belum ada target tabungan yang dibuat.</p>
                ) : (
                  <div className="space-y-3">
                    {savings.slice(0, 4).map((s) => {
                      const target = Number(s.target_amount || 0);
                      const current = Number(s.current_amount || 0);
                      const pct = target > 0 ? Math.min(Math.round((current / target) * 100), 100) : 0;
                      return (
                        <div key={s.id} className="p-3 bg-gray-50 rounded border text-xs space-y-1">
                          <div className="flex justify-between font-semibold text-gray-800">
                            <span>{s.name}</span>
                            <span className="text-green-700 font-bold">
                              {formatRupiah(current)} / {formatRupiah(target)} ({pct}%)
                            </span>
                          </div>
                          <div className="w-full bg-gray-200 rounded-full h-2">
                            <div className="h-2 rounded-full bg-green-600" style={{ width: `${pct}%` }}></div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>

            {/* 4 & 5. Grid Row: Upcoming Bills & Recent Transactions */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Upcoming Bills */}
              <div className="bg-white border rounded-lg p-5 space-y-4">
                <div className="flex justify-between items-center border-b pb-3">
                  <h2 className="text-lg font-bold text-gray-800">Tagihan Mendatang (Upcoming Bills)</h2>
                  <Link to="/recurring-transactions" className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Lihat Semua &rarr;</Link>
                </div>
                {upcomingBills.length === 0 ? (
                  <p className="text-xs text-gray-500 py-4 text-center">Tidak ada tagihan rutin terdekat.</p>
                ) : (
                  <div className="space-y-2">
                    {upcomingBills.slice(0, 5).map((b) => (
                      <div key={b.id} className="flex justify-between items-center p-2.5 bg-amber-50/60 border border-amber-100 rounded text-xs">
                        <div>
                          <div className="font-semibold text-gray-800">{b.description || b.name || 'Tagihan Rutin'}</div>
                          <div className="text-[10px] text-gray-500 mt-0.5">Jatuh Tempo: {b.next_due_date || b.due_date || '-'}</div>
                        </div>
                        <div className="font-bold text-red-700">{formatRupiah(b.amount)}</div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Transaksi Terbaru */}
              <div className="bg-white border rounded-lg p-5 space-y-4">
                <div className="flex justify-between items-center border-b pb-3">
                  <h2 className="text-lg font-bold text-gray-800">Transaksi Terbaru</h2>
                  <Link to="/transactions" className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Lihat Semua &rarr;</Link>
                </div>
                {transactions.length === 0 ? (
                  <p className="text-xs text-gray-500 py-4 text-center">Belum ada catatan transaksi.</p>
                ) : (
                  <div className="space-y-2">
                    {transactions.map((t) => (
                      <div key={t.id} className="flex justify-between items-center p-2.5 bg-gray-50 border rounded text-xs">
                        <div>
                          <div className="font-semibold text-gray-800">{t.description || (t.type === 'income' ? 'Pemasukan' : t.type === 'expense' ? 'Pengeluaran' : 'Transfer')}</div>
                          <div className="text-[10px] text-gray-500 mt-0.5">{t.transaction_date} • {t.account_name || 'Rekening'}</div>
                        </div>
                        <div className={`font-bold ${t.type === 'income' ? 'text-green-700' : t.type === 'expense' ? 'text-red-700' : 'text-indigo-700'}`}>
                          {t.type === 'income' ? '+' : t.type === 'expense' ? '-' : ''}{formatRupiah(t.amount)}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
