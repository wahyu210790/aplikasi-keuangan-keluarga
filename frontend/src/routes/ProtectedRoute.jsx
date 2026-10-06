import React, { useContext } from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { AuthContext } from '../context/AuthContext';

export default function ProtectedRoute() {
  const { user, loading } = useContext(AuthContext);

  if (loading) {
    return <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4"><p>Memuat...</p></div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}
