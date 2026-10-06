import React, { useEffect, useState } from 'react';
import { Outlet, Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import HouseholdSelector from '../components/HouseholdSelector';
import api from '../services/api';

export default function AppLayout() {
  const location = useLocation();
  const { user } = useAuth();

  const [unreadCount, setUnreadCount] = useState(0);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(false);

  const fetchUnreadCount = async () => {
    try {
      const res = await api.get('/notifications/unread-count');
      setUnreadCount(res.data.unread_count || 0);
    } catch (err) {
      console.error('Failed to load unread count', err);
    }
  };

  const fetchNotifications = async () => {
    setLoading(true);
    try {
      const res = await api.get('/notifications');
      setNotifications(res.data.data || []);
    } catch (err) {
      console.error('Failed to load notifications', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUnreadCount();
    const interval = setInterval(fetchUnreadCount, 30000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isDrawerOpen) {
        setIsDrawerOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isDrawerOpen]);

  const handleOpenDrawer = () => {
    setIsDrawerOpen(true);
    fetchNotifications();
  };

  const handleMarkAsRead = async (id) => {
    try {
      await api.patch(`/notifications/${id}/read`);
      fetchNotifications();
      fetchUnreadCount();
    } catch (err) {
      console.error('Failed to mark notification as read', err);
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await api.post('/notifications/mark-all-read');
      fetchNotifications();
      fetchUnreadCount();
    } catch (err) {
      console.error('Failed to mark all as read', err);
    }
  };

  const navLinks = [
    { path: '/', label: 'Dashboard' },
    { path: '/accounts', label: 'Akun' },
    { path: '/transactions', label: 'Transaksi' },
    { path: '/recurring-transactions', label: 'Rutin' },
    { path: '/budgets', label: 'Anggaran' },
    { path: '/savings', label: 'Tabungan' },
    { path: '/reports', label: 'Laporan' },
    { path: '/subscription', label: 'Langganan' },
    { path: '/currencies', label: 'Kurs' },
    { path: '/activity-logs', label: 'Audit Log' },
    { path: '/household/members', label: 'Anggota' },
    { path: '/profile', label: 'Profil' },
  ];

  if (user?.global_role === 'super_admin') {
    navLinks.push({ path: '/admin', label: 'Super Admin' });
  }

  return (
    <div className="min-h-screen flex flex-col bg-gray-100">
      <header className="bg-indigo-600 text-white py-3 px-6 shadow-md">
        <div className="flex justify-between items-center flex-wrap gap-4">
          <div className="flex items-center space-x-6">
            <Link to="/" className="text-xl font-bold tracking-wide hover:opacity-90">
              Keuangan Keluarga
            </Link>
            <nav className="hidden md:flex space-x-1">
              {navLinks.map((link) => {
                const isActive = location.pathname === link.path;
                return (
                  <Link
                    key={link.path}
                    to={link.path}
                    className={`px-3 py-1.5 rounded-md text-sm font-medium transition ${
                      isActive
                        ? 'bg-indigo-700 text-white'
                        : 'text-indigo-100 hover:bg-indigo-500 hover:text-white'
                    }`}
                  >
                    {link.label}
                  </Link>
                );
              })}
            </nav>
          </div>

          <div className="flex items-center space-x-4">
            {/* Notification Bell Indicator */}
            <button
              onClick={handleOpenDrawer}
              className="relative p-1.5 bg-indigo-700 hover:bg-indigo-800 rounded-full focus:outline-none"
              title="Notifikasi"
            >
              <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              {unreadCount > 0 && (
                <span className="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full animate-pulse">
                  {unreadCount > 9 ? '9+' : unreadCount}
                </span>
              )}
            </button>

            <HouseholdSelector />
          </div>
        </div>

        {/* Mobile Navigation bar */}
        <nav className="flex md:hidden space-x-1 mt-3 pt-2 border-t border-indigo-500 overflow-x-auto">
          {navLinks.map((link) => {
            const isActive = location.pathname === link.path;
            return (
              <Link
                key={link.path}
                to={link.path}
                className={`px-3 py-1 rounded text-xs font-medium whitespace-nowrap transition ${
                  isActive
                    ? 'bg-indigo-700 text-white'
                    : 'text-indigo-100 hover:bg-indigo-500'
                }`}
              >
                {link.label}
              </Link>
            );
          })}
        </nav>
      </header>

      {/* Notification Slide-Over Drawer */}
      {isDrawerOpen && (
        <div className="fixed inset-0 z-50 overflow-hidden bg-black bg-opacity-50" role="dialog" aria-modal="true" aria-labelledby="notification-drawer-title">
          <div className="absolute inset-y-0 right-0 max-w-full flex pl-10">
            <div className="w-screen max-w-md bg-white shadow-xl flex flex-col">
              <div className="p-4 bg-indigo-600 text-white flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <h2 id="notification-drawer-title" className="text-lg font-bold">Notifikasi</h2>
                  {unreadCount > 0 && (
                    <span className="bg-red-500 text-xs px-2 py-0.5 rounded-full font-bold">
                      {unreadCount} baru
                    </span>
                  )}
                </div>
                <button
                  onClick={() => setIsDrawerOpen(false)}
                  aria-label="Tutup notifikasi"
                  className="text-white hover:text-indigo-200 text-2xl font-bold focus:outline-none focus:ring-2 focus:ring-white rounded px-1"
                >
                  &times;
                </button>
              </div>

              <div className="p-3 border-b bg-gray-50 flex justify-between items-center text-xs">
                <span className="text-gray-500">Pemberitahuan & Alert</span>
                <button
                  onClick={handleMarkAllRead}
                  className="text-indigo-600 hover:text-indigo-800 font-semibold"
                >
                  Tandai Semua Dibaca
                </button>
              </div>

              <div className="flex-1 overflow-y-auto p-4 space-y-3">
                {loading ? (
                  <div className="text-center py-8 text-gray-500 text-sm">Memuat notifikasi...</div>
                ) : notifications.length === 0 ? (
                  <div className="text-center py-8 text-gray-500 text-sm">Belum ada notifikasi saat ini.</div>
                ) : (
                  notifications.map((n) => (
                    <div
                      key={n.id}
                      className={`p-3 rounded-lg border text-sm transition ${
                        n.is_read ? 'bg-white border-gray-100 text-gray-600' : 'bg-indigo-50/50 border-indigo-100 text-gray-900 font-medium'
                      }`}
                    >
                      <div className="flex justify-between items-start">
                        <span className="font-bold text-gray-900">{n.title}</span>
                        {!n.is_read && (
                          <button
                            onClick={() => handleMarkAsRead(n.id)}
                            className="text-[10px] bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded hover:bg-indigo-200"
                          >
                            Tandai dibaca
                          </button>
                        )}
                      </div>
                      <p className="text-xs text-gray-600 mt-1">{n.message}</p>
                      <span className="text-[10px] text-gray-400 mt-2 block">
                        {n.created_at ? new Date(n.created_at).toLocaleString('id-ID') : '-'}
                      </span>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      <main className="flex-1 p-6 bg-gray-100">
        <Outlet />
      </main>
    </div>
  );
}
