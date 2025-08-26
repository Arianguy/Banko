import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import AssetAllocationChart from '@/components/AssetAllocationChart';

interface EquityData {
    total_invested: number;
    realized_pl: number;
    unrealized_pl: number;
    current_value: number;
    total_dividends: number;
}

interface FixedDepositData {
    total_principal: number;
    total_unrealized_interest: number;
    bank_wise_data: {
        [bankName: string]: {
            principal: number;
            interest: number;
        };
    };
}

interface BankBalanceData {
    bank_balances: {
        bank_name: string;
        account_number: string;
        balance: number;
        update_date: string;
    }[];
    total_balance: number;
}

interface MutualFundData {
    total_investment: number;
    total_current_value: number;
    total_pl: number;
    fund_wise_data: {
        scheme_name: string;
        fund_house: string;
        units: number;
        investment: number;
        current_value: number;
        pl: number;
    }[];
}

interface DashboardProps {
    equity_data: EquityData;
    fixed_deposit_data: FixedDepositData;
    bank_balance_data: BankBalanceData;
    mutual_fund_data: MutualFundData;
}

function formatINR(amount: number) {
    return '₹ ' + Number(Math.round(amount)).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({ equity_data, fixed_deposit_data, bank_balance_data, mutual_fund_data }: DashboardProps) {
    
    // Safety checks for equity_data
    const safeEquityData = {
        total_invested: equity_data?.total_invested || 0,
        realized_pl: equity_data?.realized_pl || 0,
        unrealized_pl: equity_data?.unrealized_pl || 0,
        current_value: equity_data?.current_value || 0,
        total_dividends: equity_data?.total_dividends || 0,
    };

    // Safety checks for fixed_deposit_data
    const safeFixedDepositData = {
        total_principal: fixed_deposit_data?.total_principal || 0,
        total_unrealized_interest: fixed_deposit_data?.total_unrealized_interest || 0,
        bank_wise_data: fixed_deposit_data?.bank_wise_data || {},
    };

    // Safety checks for bank_balance_data
    const safeBankBalanceData = {
        bank_balances: bank_balance_data?.bank_balances || [],
        total_balance: bank_balance_data?.total_balance || 0,
    };

    // Safety checks for mutual_fund_data
    const safeMutualFundData = {
        total_investment: mutual_fund_data?.total_investment || 0,
        total_current_value: mutual_fund_data?.total_current_value || 0,
        total_pl: mutual_fund_data?.total_pl || 0,
        fund_wise_data: mutual_fund_data?.fund_wise_data || [],
    };



    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-2 sm:p-4">

                
                <div className="grid auto-rows-min gap-2 sm:gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-lg border bg-blue-50 p-2 sm:p-3">
                        <h3 className="text-sm sm:text-base font-semibold text-blue-900 mb-1 sm:mb-2">Net Worth</h3>
                        <div className="text-xs sm:text-sm space-y-1">
                            <div className="flex justify-between">
                                <span className="text-gray-600">Principal Net:</span>
                                <span className="font-medium text-blue-700">{formatINR(Math.round(safeFixedDepositData.total_principal + safeEquityData.total_invested + safeBankBalanceData.total_balance + safeMutualFundData.total_investment))}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Unrealised Net:</span>
                                <span className="font-medium text-green-600">{formatINR(Math.round(safeFixedDepositData.total_unrealized_interest + safeEquityData.unrealized_pl + safeMutualFundData.total_pl))}</span>
                            </div>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-lg border bg-orange-50 p-2 sm:p-3">
                        <h3 className="text-sm sm:text-base font-semibold text-orange-900 mb-1 sm:mb-2">Fixed Deposits</h3>
                        <div className="text-xs sm:text-sm space-y-1">
                            <div className="flex justify-between">
                                 <span className="text-gray-600">Principal:</span>
                                 <span className="font-medium text-orange-700">{formatINR(Math.round(safeFixedDepositData.total_principal))}</span>
                             </div>
                             <div className="flex justify-between">
                                 <span className="text-gray-600">Interest (unrealised):</span>
                                 <span className="font-medium text-green-600">{formatINR(Math.round(safeFixedDepositData.total_unrealized_interest))}</span>
                             </div>
                            <div className="border-t pt-1 mt-1 space-y-1">
                                {Object.entries(safeFixedDepositData.bank_wise_data).map(([bank, data]) => (
                                    <div key={bank} className="flex justify-between text-xs">
                                        <span className="text-gray-600">{bank}:</span>
                                        <span className="font-medium">
                                             {formatINR(Math.round(data.principal))} || <span className="text-green-600">{formatINR(Math.round(data.interest))}</span>
                                         </span>
                                    </div>
                                ))}
                                {Object.keys(safeFixedDepositData.bank_wise_data).length === 0 && (
                                    <div className="text-gray-500 text-center text-xs">No Fixed Deposits</div>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-lg border bg-gray-50 p-2 sm:p-3">
                        <h3 className="text-sm sm:text-base font-semibold text-gray-900 mb-1 sm:mb-2">Equity Summary</h3>
                        <div className="text-xs sm:text-sm space-y-1">
                            <div className="flex justify-between">
                                 <span className="text-gray-600">Invested:</span>
                                 <span className="font-medium text-blue-700">{formatINR(Math.round(safeEquityData.total_invested))}</span>
                             </div>
                             <div className="flex justify-between">
                                 <span className="text-gray-600">Realised P/L:</span>
                                 <span className={`font-medium ${
                                     safeEquityData.realized_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                 }`}>
                                     {formatINR(Math.round(safeEquityData.realized_pl))}
                                 </span>
                             </div>
                             <div className="flex justify-between">
                                 <span className="text-gray-600">Unrealised P/L:</span>
                                 <span className={`font-medium ${
                                     safeEquityData.unrealized_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                 }`}>
                                     {formatINR(Math.round(safeEquityData.unrealized_pl))}
                                 </span>
                             </div>
                             <div className="flex justify-between">
                                 <span className="text-gray-600">Dividends:</span>
                                 <span className="font-medium text-purple-600">{formatINR(Math.round(safeEquityData.total_dividends))}</span>
                             </div>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-lg border bg-green-50 p-2 sm:p-3">
                        <h3 className="text-sm sm:text-base font-semibold text-green-900 mb-1 sm:mb-2">Banks Balance</h3>
                        <div className="text-xs sm:text-sm space-y-1">
                            <div className="flex justify-between mb-1">
                                <span className="text-gray-600">Balance:</span>
                                <span className="font-medium text-green-700">{formatINR(Math.round(safeBankBalanceData.total_balance))}</span>
                            </div>
                            <div className="border-t pt-1 mt-1 space-y-1">
                                {safeBankBalanceData.bank_balances.map((balance, index) => (
                                    <div key={index} className="flex justify-between text-xs">
                                        <span className="text-gray-600 truncate" title={`${balance.bank_name} (${balance.account_number})`}>
                                            {balance.bank_name}:
                                        </span>
                                        <span className="font-medium text-green-600">
                                            {formatINR(Math.round(balance.balance))}
                                        </span>
                                    </div>
                                ))}
                                {safeBankBalanceData.bank_balances.length === 0 && (
                                    <div className="text-gray-500 text-center text-xs">No Bank Balances</div>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-lg border bg-purple-50 p-2 sm:p-3">
                        <h3 className="text-sm sm:text-base font-semibold text-purple-900 mb-1 sm:mb-2">Mutual Funds</h3>
                        <div className="text-xs sm:text-sm space-y-1">
                            <div className="flex justify-between">
                                <span className="text-gray-600">Investment:</span>
                                <span className="font-medium text-purple-700">{formatINR(Math.round(safeMutualFundData.total_investment))}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Current Value:</span>
                                <span className="font-medium text-purple-700">{formatINR(Math.round(safeMutualFundData.total_current_value))}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">P/L:</span>
                                <span className={`font-medium ${
                                    safeMutualFundData.total_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                }`}>
                                    {formatINR(Math.round(safeMutualFundData.total_pl))}
                                </span>
                            </div>
                            <div className="border-t pt-1 mt-1 space-y-1">
                                {safeMutualFundData.fund_wise_data.map((fund, index) => (
                                    <div key={index} className="flex justify-between text-xs">
                                        <span className="text-gray-600 truncate" title={fund.scheme_name}>
                                            {fund.fund_house}:
                                        </span>
                                        <span className="font-medium text-purple-600">
                                            {formatINR(Math.round(fund.current_value))}
                                        </span>
                                    </div>
                                ))}
                                {safeMutualFundData.fund_wise_data.length === 0 && (
                                    <div className="text-gray-500 text-center text-xs">No Mutual Funds</div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 overflow-hidden rounded-xl border bg-white p-6">
                    <div className="h-full flex flex-col">
                        <h2 className="text-lg sm:text-xl font-semibold text-gray-900 mb-2 sm:mb-4">Portfolio Overview</h2>
                        
                        {/* Current Value Card */}
                        <div className="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-4 sm:p-6 mb-4 sm:mb-6">
                            <h3 className="text-base sm:text-lg font-medium text-gray-700 mb-2">Current Portfolio Value</h3>
                            <p className="text-2xl sm:text-3xl font-bold text-green-600">
                                 {formatINR(safeEquityData.current_value)}
                             </p>
                             <div className="mt-2 text-sm text-gray-600">
                                 <span className="inline-flex items-center">
                                     Total Return: 
                                     <span className={`ml-1 font-semibold ${
                                         (safeEquityData.current_value - safeEquityData.total_invested) >= 0 
                                             ? 'text-green-600' 
                                             : 'text-red-600'
                                     }`}>
                                         {formatINR(safeEquityData.current_value - safeEquityData.total_invested)}
                                         ({safeEquityData.total_invested > 0 ? (((safeEquityData.current_value - safeEquityData.total_invested) / safeEquityData.total_invested) * 100).toFixed(2) : '0.00'}%)
                                     </span>
                                 </span>
                             </div>
                        </div>
                        
                        {/* Summary Stats */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2 sm:gap-4 mb-4 sm:mb-6">
                            <div className="bg-gray-50 rounded-lg p-3 sm:p-4">
                                <h4 className="text-sm sm:text-base font-medium text-gray-700 mb-2">Investment Summary</h4>
                                <div className="space-y-1 sm:space-y-2 text-xs sm:text-sm">
                                    <div className="flex justify-between">
                                         <span className="text-gray-600">Total Invested:</span>
                                         <span className="font-medium">{formatINR(safeEquityData.total_invested)}</span>
                                     </div>
                                     <div className="flex justify-between">
                                         <span className="text-gray-600">Current Value:</span>
                                         <span className="font-medium">{formatINR(safeEquityData.current_value)}</span>
                                     </div>
                                     <div className="flex justify-between border-t pt-2">
                                         <span className="text-gray-600">Net Gain/Loss:</span>
                                         <span className={`font-medium ${
                                             (safeEquityData.current_value - safeEquityData.total_invested) >= 0 
                                                 ? 'text-green-600' 
                                                 : 'text-red-600'
                                         }`}>
                                             {formatINR(safeEquityData.current_value - safeEquityData.total_invested)}
                                         </span>
                                     </div>
                                </div>
                            </div>
                            
                            <div className="bg-gray-50 rounded-lg p-3 sm:p-4">
                                <h4 className="text-sm sm:text-base font-medium text-gray-700 mb-2">P&L Breakdown</h4>
                                <div className="space-y-1 sm:space-y-2 text-xs sm:text-sm">
                                    <div className="flex justify-between">
                                         <span className="text-gray-600">Realised P/L:</span>
                                         <span className={`font-medium ${
                                             safeEquityData.realized_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                         }`}>
                                             {formatINR(safeEquityData.realized_pl)}
                                         </span>
                                     </div>
                                     <div className="flex justify-between">
                                         <span className="text-gray-600">Unrealised P/L:</span>
                                         <span className={`font-medium ${
                                             safeEquityData.unrealized_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                         }`}>
                                             {formatINR(safeEquityData.unrealized_pl)}
                                         </span>
                                     </div>
                                     <div className="flex justify-between border-t pt-2">
                                         <span className="text-gray-600">Total P/L:</span>
                                         <span className={`font-medium ${
                                             (safeEquityData.realized_pl + safeEquityData.unrealized_pl) >= 0 
                                                 ? 'text-green-600' 
                                                 : 'text-red-600'
                                         }`}>
                                             {formatINR(safeEquityData.realized_pl + safeEquityData.unrealized_pl)}
                                         </span>
                                     </div>
                                </div>
                            </div>
                        </div>
                        
                        {/* Asset Allocation Chart */}
                        <div className="mb-4 sm:mb-6">
                            <AssetAllocationChart 
                        realizedPL={safeEquityData.realized_pl}
                        totalDividends={safeEquityData.total_dividends}
                        fixedDepositPrincipal={safeFixedDepositData.total_principal}
                        bankBalanceTotal={safeBankBalanceData.total_balance}
                        equityInvested={safeEquityData.total_invested}
                        mutualFundInvestment={safeMutualFundData.total_investment}
                    />
                        </div>
                        
                        {/* Quick Actions */}
                        <div className="mt-auto">
                            <h4 className="text-sm sm:text-base font-medium text-gray-700 mb-2 sm:mb-3">Quick Actions</h4>
                            <div className="flex flex-wrap gap-1 sm:gap-2">
                                <button 
                                    onClick={() => router.visit('/equity-holding')}
                                    className="px-2 sm:px-4 py-1 sm:py-2 text-xs sm:text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                                >
                                    View Holdings
                                </button>
                                <button 
                                    onClick={() => router.visit('/equity-holding')}
                                    className="px-2 sm:px-4 py-1 sm:py-2 text-xs sm:text-sm bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                                >
                                    Add Transaction
                                </button>
                                <button 
                                    onClick={() => router.visit('/dividend')}
                                    className="px-2 sm:px-4 py-1 sm:py-2 text-xs sm:text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
                                >
                                    View Dividends
                                </button>
                                <button 
                                    onClick={() => router.visit('/fixed-deposits')}
                                    className="px-2 sm:px-4 py-1 sm:py-2 text-xs sm:text-sm bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors"
                                >
                                    View Fixed Deposits
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
