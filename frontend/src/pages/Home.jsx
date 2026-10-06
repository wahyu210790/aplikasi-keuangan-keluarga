import React, { useState, useContext } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { AuthContext } from '../context/AuthContext';

export default function Home() {
  const navigate = useNavigate();
  const { logout } = useContext(AuthContext);


  const handleLogout = async () => {
    logout();
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
      <div className="rounded-lg bg-white p-8 shadow-md text-center max-w-md w-full">
        <h1 className="text-2xl font-bold text-indigo-600 mb-4">Household Finance SaaS</h1>
        <p className="text-gray-600 mb-6">Infrastructure Frontend React &amp; Tailwind CSS Siap.</p>
        <button
          onClick={handleLogout}
          disabled={loading}
          className="w-full bg-red-600 text-white font-semibold py-2 rounded hover:bg-red-700 disabled:opacity-50"
        >
          {loading ? 'Memproses...' : 'Logout'}
        </button>
      </div>
    </div>
  );
}
