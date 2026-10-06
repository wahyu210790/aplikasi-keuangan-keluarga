import React, { useEffect, useState, useRef } from 'react';
import { useHousehold } from '../context/HouseholdContext';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

export default function ActivityLogs() {
  const { activeHouseholdId, loading: contextLoading } = useHousehold();

  const [logs, setLogs] = useState([]);
  const [summary, setSummary] = useState({ total_logs: 0, action_counts: {} });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Filters
  const [search, setSearch] = useState('');
  const [action, setAction] = useState('');
  const [entityType, setEntityType] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);

  const requestIdRef = useRef(0);

  const fetchSummary = async () => {
    if (!activeHouseholdId) return;
    try {
      const params = {};
      if (startDate) params.start_date = startDate;
      if (endDate) params.end_date = endDate;
      const res = await api.get(`/households/${activeHouseholdId}/activity-logs/summary`, { params });
      setSummary(res.data || { total_logs: 0, action_counts: {} });
    } catch (err) {
      console.error('Failed to load activity summary', err);
    }
  };

  const fetchLogs = async () => {
    if (!activeHouseholdId) return;
    const currentReqId = ++requestIdRef.current;
    setLoading(true);
    setError(null);

    try {
      const params = { page };
      if (search) params.search = search;
      if (action) params.action = action;
      if (entityType) params.entity_type = entityType;
      if (startDate) params.start_date = startDate;
      if (endDate) params.end_date = endDate;

      const response = await api.get(`/households/${activeHouseholdId}/activity-logs`, { params });
      if (currentReqId === requestIdRef.current) {
        const paginated = response.data;
        setLogs(paginated.data || []);
        setPage(paginated.current_page || 1);
        setLastPage(paginated.last_page || 1);
        setTotalItems(paginated.total || 0);
        setLoading(false);
      }
    } catch (err) {
      if (currentReqId === requestIdRef.current) {
        setError(err.response?.data?.message || 'Gagal memuat log aktivitas');
        setLoading(false);
      }
    }
  };

  useEffect(() => {
    if (activeHouseholdId) {
      fetchLogs();
      fetchSummary();
    }
  }, [activeHouseholdId, page, action, entityType, startDate, endDate]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    fetchLogs();
    fetchSummary();
  };

  const handleResetFilters = () => {
    setSearch('');
    setAction('');
    setEntityType('');
    setStartDate('');
    setEndDate('');
    setPage(1);
  };

  const getActionBadge = (act) => {
    switch (act?.toLowerCase()) {
      case 'create':
        return 'bg-green-100 text-green-800';
      case 'update':
        return 'bg-blue-100 text-blue-800';
      case 'delete':
        return 'bg-red-100 text-red-800';
      case 'process':
        return 'bg-purple-100 text-purple-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  if (contextLoading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/4"></div>
          <div className="h-48 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Audit & Log Aktivitas</h1>
        <p className="text-gray-600 text-sm">Jejak audit seluruh perubahan data dan aktivitas anggota household</p>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card className="p-4 bg-white border border-gray-100 shadow-sm">
          <span className="text-xs font-semibold text-gray-500 uppercase">Total Aktivitas</span>
          <div className="text-2xl font-bold text-gray-900 mt-1">{summary.total_logs || 0}</div>
        </Card>
        <Card className="p-4 bg-white border border-gray-100 shadow-sm">
          <span className="text-xs font-semibold text-green-600 uppercase">Penambahan (Create)</span>
          <div className="text-2xl font-bold text-green-700 mt-1">{summary.action_counts?.create || 0}</div>
        </Card>
        <Card className="p-4 bg-white border border-gray-100 shadow-sm">
          <span className="text-xs font-semibold text-blue-600 uppercase">Perubahan (Update)</span>
          <div className="text-2xl font-bold text-blue-700 mt-1">{summary.action_counts?.update || 0}</div>
        </Card>
        <Card className="p-4 bg-white border border-gray-100 shadow-sm">
          <span className="text-xs font-semibold text-red-600 uppercase">Penghapusan (Delete)</span>
          <div className="text-2xl font-bold text-red-700 mt-1">{summary.action_counts?.delete || 0}</div>
        </Card>
      </div>

      {error && (
        <Alert type="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* Filter Section */}
      <Card className="p-4 bg-white">
        <form onSubmit={handleSearchSubmit} className="space-y-3">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <Input
              type="text"
              placeholder="Cari deskripsi log..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="text-sm"
            />
            <div>
              <select
                value={action}
                onChange={(e) => { setAction(e.target.value); setPage(1); }}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2"
              >
                <option value="">Semua Aksi</option>
                <option value="create">Create (Tambah)</option>
                <option value="update">Update (Ubah)</option>
                <option value="delete">Delete (Hapus)</option>
                <option value="process">Process (Proses)</option>
              </select>
            </div>
            <div>
              <select
                value={entityType}
                onChange={(e) => { setEntityType(e.target.value); setPage(1); }}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2"
              >
                <option value="">Semua Entitas</option>
                <option value="transaction">Transaksi</option>
                <option value="account">Akun / Rekening</option>
                <option value="budget">Anggaran</option>
                <option value="saving">Tabungan</option>
                <option value="recurring_transaction">Transaksi Rutin</option>
                <option value="household">Household</option>
              </select>
            </div>
            <div className="flex space-x-2">
              <Button type="submit" className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
                Cari
              </Button>
              <Button type="button" variant="secondary" onClick={handleResetFilters} className="text-sm">
                Reset
              </Button>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-gray-100">
            <Input
              label="Dari Tanggal"
              type="date"
              value={startDate}
              onChange={(e) => { setStartDate(e.target.value); setPage(1); }}
            />
            <Input
              label="Sampai Tanggal"
              type="date"
              value={endDate}
              onChange={(e) => { setEndDate(e.target.value); setPage(1); }}
            />
          </div>
        </form>
      </Card>

      {/* Logs Table */}
      <Card>
        {loading ? (
          <div className="p-8 text-center text-gray-500">Memuat log aktivitas...</div>
        ) : logs.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            Tidak ada log aktivitas yang cocok dengan kriteria pencarian.
          </div>
        ) : (
          <div>
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <th className="py-3 px-4">Waktu</th>
                    <th className="py-3 px-4">Pengguna</th>
                    <th className="py-3 px-4">Aksi</th>
                    <th className="py-3 px-4">Entitas</th>
                    <th className="py-3 px-4">Deskripsi</th>
                    <th className="py-3 px-4">IP Address</th>
                  </tr>
                </thead>
                <tbody className="divide-y text-sm">
                  {logs.map((item) => (
                    <tr key={item.id} className="hover:bg-gray-50">
                      <td className="py-3 px-4 text-xs text-gray-500 whitespace-nowrap">
                        {item.created_at ? new Date(item.created_at).toLocaleString('id-ID') : '-'}
                      </td>
                      <td className="py-3 px-4 font-medium text-gray-900">
                        {item.user ? item.user.name : 'Sistem / Anonim'}
                      </td>
                      <td className="py-3 px-4">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium uppercase ${getActionBadge(item.action)}`}>
                          {item.action}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-xs font-mono text-gray-600 capitalize">
                        {item.entity_type || '-'}
                      </td>
                      <td className="py-3 px-4 text-gray-800">{item.description || '-'}</td>
                      <td className="py-3 px-4 text-xs font-mono text-gray-400">{item.ip_address || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            <div className="p-4 border-t flex items-center justify-between text-sm text-gray-600">
              <div>
                Menampilkan halaman <strong>{page}</strong> dari <strong>{lastPage}</strong> (Total: {totalItems} log)
              </div>
              <div className="flex space-x-2">
                <Button
                  variant="secondary"
                  disabled={page <= 1 || loading}
                  onClick={() => setPage(page - 1)}
                  className="text-xs"
                >
                  &laquo; Sebelumnya
                </Button>
                <Button
                  variant="secondary"
                  disabled={page >= lastPage || loading}
                  onClick={() => setPage(page + 1)}
                  className="text-xs"
                >
                  Berikutnya &raquo;
                </Button>
              </div>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
