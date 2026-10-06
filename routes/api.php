<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\HouseholdController;
use App\Http\Controllers\Api\V1\HouseholdMemberController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\SavingController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RecurringTransactionController;

Route::post('v1/login', [AuthController::class, 'login']);
Route::post('v1/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('v1/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::post('v1/households', [HouseholdController::class, 'store'])->middleware('auth:sanctum');
Route::get('v1/households/{household}', [HouseholdController::class, 'show'])->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}', [HouseholdController::class, 'update'])->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/settings', [HouseholdController::class, 'settings'])->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/settings', [HouseholdController::class, 'updateSettings'])->middleware(['auth:sanctum', 'household.member']);

// Household member management
Route::get('v1/households/{household}/members', [HouseholdMemberController::class, 'index'])->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/members', [HouseholdMemberController::class, 'store'])->middleware('auth:sanctum');
Route::patch('v1/households/{household}/members/{member}', [HouseholdMemberController::class, 'update'])->middleware('auth:sanctum');
Route::delete('v1/households/{household}/members/{member}', [HouseholdMemberController::class, 'destroy'])->middleware('auth:sanctum');

// Account management
Route::get('v1/households/{household}/accounts', [AccountController::class, 'index'])->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/accounts', [AccountController::class, 'store'])->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/accounts/{account}', [AccountController::class, 'update'])->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/accounts/{account}/archive', [AccountController::class, 'archive'])->middleware(['auth:sanctum', 'household.member']);

// Transaction routes
Route::get('v1/households/{household}/transactions', [TransactionController::class, 'index'])->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/transactions', [TransactionController::class, 'store'])->name('api.v1.households.transactions.store')->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/transactions/{transaction}', [TransactionController::class, 'show'])->name('api.v1.households.transactions.show')->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/transactions/{transaction}', [TransactionController::class, 'update'])->name('api.v1.households.transactions.update')->middleware(['auth:sanctum', 'household.member']);
Route::delete('v1/households/{household}/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('api.v1.households.transactions.destroy')->middleware(['auth:sanctum', 'household.member']);

// Category routes
Route::get('v1/households/{household}/categories', [CategoryController::class, 'index'])
    ->name('api.v1.households.categories.index')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/categories', [CategoryController::class, 'store'])
    ->name('api.v1.households.categories.store')
    ->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/categories/{category}', [CategoryController::class, 'update'])
    ->name('api.v1.households.categories.update')
    ->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/categories/{category}/archive', [CategoryController::class, 'archive'])
    ->name('api.v1.households.categories.archive')
    ->middleware(['auth:sanctum', 'household.member']);

// Budget routes
Route::get('v1/households/{household}/budgets/alerts', [\App\Http\Controllers\Api\V1\BudgetAlertController::class, 'alerts'])
    ->name('api.v1.households.budgets.alerts')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/budgets', [BudgetController::class, 'index'])
    ->name('api.v1.households.budgets.index')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/budgets', [BudgetController::class, 'store'])
    ->name('api.v1.households.budgets.store')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/budgets/{budget}', [BudgetController::class, 'show'])
    ->name('api.v1.households.budgets.show')
    ->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/budgets/{budget}', [BudgetController::class, 'update'])
    ->name('api.v1.households.budgets.update')
    ->middleware(['auth:sanctum', 'household.member']);
Route::delete('v1/households/{household}/budgets/{budget}', [BudgetController::class, 'destroy'])
    ->name('api.v1.households.budgets.destroy')
    ->middleware(['auth:sanctum', 'household.member']);

// Savings routes
Route::get('v1/households/{household}/savings', [SavingController::class, 'index'])
    ->name('api.v1.households.savings.index')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/savings', [SavingController::class, 'store'])
    ->name('api.v1.households.savings.store')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/savings/{saving}', [SavingController::class, 'show'])
    ->name('api.v1.households.savings.show')
    ->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/savings/{saving}', [SavingController::class, 'update'])
    ->name('api.v1.households.savings.update')
    ->middleware(['auth:sanctum', 'household.member']);
Route::delete('v1/households/{household}/savings/{saving}', [SavingController::class, 'destroy'])
    ->name('api.v1.households.savings.destroy')
    ->middleware(['auth:sanctum', 'household.member']);

// Reports routes (Phase 10)
Route::get('v1/households/{household}/reports/summary', [ReportController::class, 'summary'])
    ->name('api.v1.households.reports.summary')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/reports/income-vs-expense', [ReportController::class, 'incomeVsExpense'])
    ->name('api.v1.households.reports.incomeVsExpense')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/reports/category-breakdown', [ReportController::class, 'categoryBreakdown'])
    ->name('api.v1.households.reports.categoryBreakdown')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/reports/account-balances', [ReportController::class, 'accountBalances'])
    ->name('api.v1.households.reports.accountBalances')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/reports/month-comparison', [ReportController::class, 'monthComparison'])
    ->name('api.v1.households.reports.monthComparison')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/reports/savings', [ReportController::class, 'savings'])
    ->name('api.v1.households.reports.savings')
    ->middleware(['auth:sanctum', 'household.member']);

// Recurring Transactions routes (Phase 11)
Route::get('v1/households/{household}/recurring-transactions', [RecurringTransactionController::class, 'index'])
    ->name('api.v1.households.recurring-transactions.index')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/recurring-transactions/upcoming-bills', [RecurringTransactionController::class, 'upcomingBills'])
    ->name('api.v1.households.recurring-transactions.upcoming-bills')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/recurring-transactions', [RecurringTransactionController::class, 'store'])
    ->name('api.v1.households.recurring-transactions.store')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/recurring-transactions/{recurring}', [RecurringTransactionController::class, 'show'])
    ->name('api.v1.households.recurring-transactions.show')
    ->middleware(['auth:sanctum', 'household.member']);
Route::patch('v1/households/{household}/recurring-transactions/{recurring}', [RecurringTransactionController::class, 'update'])
    ->name('api.v1.households.recurring-transactions.update')
    ->middleware(['auth:sanctum', 'household.member']);
Route::delete('v1/households/{household}/recurring-transactions/{recurring}', [RecurringTransactionController::class, 'destroy'])
    ->name('api.v1.households.recurring-transactions.destroy')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/recurring-transactions/{recurring}/process', [RecurringTransactionController::class, 'process'])
    ->name('api.v1.households.recurring-transactions.process')
    ->middleware(['auth:sanctum', 'household.member']);

// Activity Log Viewer routes (Phase 12)
Route::get('v1/households/{household}/activity-logs', [\App\Http\Controllers\Api\V1\ActivityLogViewerController::class, 'index'])
    ->name('api.v1.households.activity-logs.index')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/activity-logs/summary', [\App\Http\Controllers\Api\V1\ActivityLogViewerController::class, 'summary'])
    ->name('api.v1.households.activity-logs.summary')
    ->middleware(['auth:sanctum', 'household.member']);

// Subscriptions & Plans routes (Phase 13)
Route::get('v1/plans', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'plans'])
    ->name('api.v1.plans.index')
    ->middleware(['auth:sanctum']);
Route::get('v1/households/{household}/subscription', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'show'])
    ->name('api.v1.households.subscription.show')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/subscription', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'store'])
    ->name('api.v1.households.subscription.store')
    ->middleware(['auth:sanctum', 'household.member']);
// Data Export routes (Phase 15)
Route::get('v1/households/{household}/export/transactions', [\App\Http\Controllers\Api\V1\DataExportController::class, 'transactions'])
    ->name('api.v1.households.export.transactions')
    ->middleware(['auth:sanctum', 'household.member']);
Route::get('v1/households/{household}/export/summary', [\App\Http\Controllers\Api\V1\DataExportController::class, 'summary'])
    ->name('api.v1.households.export.summary')
    ->middleware(['auth:sanctum', 'household.member']);

// Household Invitation routes (Phase 16)
Route::get('v1/households/{household}/invitations', [\App\Http\Controllers\Api\V1\HouseholdInvitationController::class, 'index'])
    ->name('api.v1.households.invitations.index')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/households/{household}/invitations', [\App\Http\Controllers\Api\V1\HouseholdInvitationController::class, 'store'])
    ->name('api.v1.households.invitations.store')
    ->middleware(['auth:sanctum', 'household.member']);
Route::post('v1/invitations/accept', [\App\Http\Controllers\Api\V1\HouseholdInvitationController::class, 'accept'])
    ->name('api.v1.invitations.accept')
    ->middleware(['auth:sanctum']);
// User Profile & Security routes (Phase 17)
Route::get('v1/profile', [\App\Http\Controllers\Api\V1\UserProfileController::class, 'show'])
    ->name('api.v1.profile.show')
    ->middleware(['auth:sanctum']);
Route::patch('v1/profile', [\App\Http\Controllers\Api\V1\UserProfileController::class, 'update'])
    ->name('api.v1.profile.update')
    ->middleware(['auth:sanctum']);
Route::post('v1/profile/change-password', [\App\Http\Controllers\Api\V1\UserProfileController::class, 'changePassword'])
    ->name('api.v1.profile.change-password')
    ->middleware(['auth:sanctum']);
Route::post('v1/profile/tokens/revoke', [\App\Http\Controllers\Api\V1\UserProfileController::class, 'revokeTokens'])
    ->name('api.v1.profile.tokens.revoke')
    ->middleware(['auth:sanctum']);

// Notifications routes (Phase 18)
Route::get('v1/notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index'])
    ->name('api.v1.notifications.index')
    ->middleware(['auth:sanctum']);
Route::get('v1/notifications/unread-count', [\App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount'])
    ->name('api.v1.notifications.unreadCount')
    ->middleware(['auth:sanctum']);
Route::patch('v1/notifications/{notification}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead'])
    ->name('api.v1.notifications.markAsRead')
    ->middleware(['auth:sanctum']);
Route::post('v1/notifications/mark-all-read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead'])
    ->name('api.v1.notifications.markAllAsRead')
    ->middleware(['auth:sanctum']);

// Multi-Currency & Conversion routes (Phase 19)
Route::get('v1/currencies', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'index'])
    ->name('api.v1.currencies.index')
    ->middleware(['auth:sanctum']);
Route::post('v1/currencies/convert', [\App\Http\Controllers\Api\V1\CurrencyController::class, 'convert'])
    ->name('api.v1.currencies.convert')
    ->middleware(['auth:sanctum']);

// Super Admin Operations routes (Phase 16)
Route::middleware(['auth:sanctum', 'super_admin'])->prefix('v1/admin')->group(function () {
    Route::get('stats', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'stats'])
        ->name('api.v1.admin.stats');
    Route::get('households', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'households'])
        ->name('api.v1.admin.households.index');
    Route::get('households/{household}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'showHousehold'])
        ->name('api.v1.admin.households.show');
    Route::patch('households/{household}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'updateHousehold'])
        ->name('api.v1.admin.households.update');

    // 16.5 User Management
    Route::get('users', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'users'])
        ->name('api.v1.admin.users.index');
    Route::get('users/{user}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'showUser'])
        ->name('api.v1.admin.users.show');

    // 16.6 Reset Password
    Route::post('users/{user}/reset-password', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'resetUserPassword'])
        ->name('api.v1.admin.users.resetPassword');

    // 16.7 Plan Management
    Route::get('plans', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'plans'])
        ->name('api.v1.admin.plans.index');
    Route::post('plans', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'storePlan'])
        ->name('api.v1.admin.plans.store');
    Route::get('plans/{plan}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'showPlan'])
        ->name('api.v1.admin.plans.show');
    Route::patch('plans/{plan}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'updatePlan'])
        ->name('api.v1.admin.plans.update');
    Route::delete('plans/{plan}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'destroyPlan'])
        ->name('api.v1.admin.plans.destroy');

    // 16.8 Subscription Management
    Route::get('subscriptions', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'subscriptions'])
        ->name('api.v1.admin.subscriptions.index');
    Route::post('households/{household}/subscription', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'assignHouseholdSubscription'])
        ->name('api.v1.admin.subscriptions.assign');
    Route::patch('subscriptions/{subscription}', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'updateSubscription'])
        ->name('api.v1.admin.subscriptions.update');

    // 16.9 Global Activity Log
    Route::get('activity-logs', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'activityLogs'])
        ->name('api.v1.admin.activityLogs.index');

    // 16.10 Admin Settings
    Route::get('settings', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'getSettings'])
        ->name('api.v1.admin.settings.index');
    Route::patch('settings', [\App\Http\Controllers\Api\V1\Admin\AdminController::class, 'updateSettings'])
        ->name('api.v1.admin.settings.update');
});












