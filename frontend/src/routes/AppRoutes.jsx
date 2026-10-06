import React from 'react';
import { Routes, Route } from 'react-router-dom';
import Home from '../pages/Home';
import Login from '../pages/Login';
import HouseholdProfile from '../pages/HouseholdProfile';
import HouseholdSettings from '../pages/HouseholdSettings';
import HouseholdMembers from '../pages/HouseholdMembers';
import Accounts from '../pages/Accounts';
import Transactions from '../pages/Transactions';
import Budgets from '../pages/Budgets';
import Savings from '../pages/Savings';
import Reports from '../pages/Reports';
import RecurringTransactions from '../pages/RecurringTransactions';
import ActivityLogs from '../pages/ActivityLogs';
import Subscription from '../pages/Subscription';
import UserProfile from '../pages/UserProfile';
import CurrencyConverter from '../pages/CurrencyConverter';
import AdminDashboard from '../pages/AdminDashboard';
import ProtectedRoute from './ProtectedRoute';
import AppLayout from '../layouts/AppLayout';

// NotFound component (same as original implementation)
function NotFound() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100 p-4">
      <div className="rounded-lg bg-white p-8 shadow-md text-center max-w-md">
        <h1 className="text-2xl font-bold text-red-600 mb-2">404</h1>
        <p className="text-gray-600">Page Not Found</p>
      </div>
    </div>
  );
}

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/household/profile" element={<HouseholdProfile />} />
          <Route path="/household/settings" element={<HouseholdSettings />} />
          <Route path="/household/members" element={<HouseholdMembers />} />
          <Route path="/accounts" element={<Accounts />} />
          <Route path="/transactions" element={<Transactions />} />
          <Route path="/budgets" element={<Budgets />} />
          <Route path="/savings" element={<Savings />} />
          <Route path="/reports" element={<Reports />} />
          <Route path="/recurring-transactions" element={<RecurringTransactions />} />
          <Route path="/activity-logs" element={<ActivityLogs />} />
          <Route path="/subscription" element={<Subscription />} />
          <Route path="/profile" element={<UserProfile />} />
          <Route path="/currencies" element={<CurrencyConverter />} />
          <Route path="/admin" element={<AdminDashboard />} />
          <Route path="/admin/users" element={<AdminDashboard />} />
          <Route path="/admin/plans" element={<AdminDashboard />} />
          <Route path="/admin/subscriptions" element={<AdminDashboard />} />
          <Route path="/admin/activity-logs" element={<AdminDashboard />} />
          <Route path="/admin/settings" element={<AdminDashboard />} />
          <Route path="*" element={<NotFound />} />
        </Route>
      </Route>
    </Routes>
  );
}
