import React from 'react';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend, LabelList } from 'recharts';

interface AssetAllocationData {
    name: string;
    value: number;
    color: string;
}

interface AssetAllocationChartProps {
    realizedPL: number;
    totalDividends: number;
    fixedDepositPrincipal: number;
    bankBalanceTotal: number;
    equityInvested: number;
    mutualFundInvestment: number;
}

function formatINR(amount: number) {
    return '₹ ' + Number(Math.round(amount)).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

const AssetAllocationChart: React.FC<AssetAllocationChartProps> = ({ realizedPL, totalDividends, fixedDepositPrincipal, bankBalanceTotal, equityInvested, mutualFundInvestment }) => {
    // Prepare data for the pie chart
    const data: AssetAllocationData[] = [];
    
    // Use only invested amount for equity (excluding realized P&L)
    const totalEquity = equityInvested;
    
    // Only include positive values in the chart
    if (totalEquity > 0) {
        data.push({
            name: 'Equity (Invested)',
            value: totalEquity,
            color: '#10b981' // green-500
        });
    }
    
    // Dividends removed from chart as requested
    
    if (fixedDepositPrincipal > 0) {
        data.push({
            name: 'Fixed Deposits (Principal)',
            value: fixedDepositPrincipal,
            color: '#f59e0b' // amber-500
        });
    }
    
    if (bankBalanceTotal > 0) {
        data.push({
            name: 'Bank Balances',
            value: bankBalanceTotal,
            color: '#3b82f6' // blue-500
        });
    }
    
    if (mutualFundInvestment > 0) {
        data.push({
            name: 'Mutual Funds',
            value: mutualFundInvestment,
            color: '#8b5cf6' // violet-500
        });
    }
    
    // If no positive data, show a message
    if (data.length === 0) {
        return (
            <div className="bg-gray-50 rounded-lg p-4 h-64 flex items-center justify-center">
                <div className="text-center text-gray-500">
                    <p className="text-sm">No asset data to display</p>
                    <p className="text-xs mt-1">Chart will appear when you have positive values in equity, dividends, fixed deposits, or bank balances</p>
                </div>
            </div>
        );
    }
    
    const CustomTooltip = ({ active, payload }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0];
            return (
                <div className="bg-white p-3 border border-gray-200 rounded-lg shadow-lg">
                    <p className="font-medium text-gray-900">{data.name}</p>
                    <p className="text-sm text-gray-600">
                        <span className="font-semibold" style={{ color: data.payload.color }}>
                            {formatINR(data.value)}
                        </span>
                    </p>
                    <p className="text-xs text-gray-500">
                        {((data.value / data.payload.total) * 100).toFixed(1)}% of total
                    </p>
                </div>
            );
        }
        return null;
    };
    
    const CustomLegend = ({ payload }: any) => {
        return (
            <div className="flex flex-col space-y-2 mt-4">
                {payload.map((entry: any, index: number) => (
                    <div key={index} className="flex items-center justify-between text-sm">
                        <div className="flex items-center space-x-2">
                            <div 
                                className="w-3 h-3 rounded-full" 
                                style={{ backgroundColor: entry.color }}
                            />
                            <span className="text-gray-700">{entry.name}</span>
                        </div>
                        <span className="font-medium text-gray-900">
                            {formatINR(entry.value)}
                        </span>
                    </div>
                ))}
            </div>
        );
    };

    const renderCustomizedLabel = ({ cx, cy, midAngle, innerRadius, outerRadius, percent }: any) => {
        const RADIAN = Math.PI / 180;
        const radius = innerRadius + (outerRadius - innerRadius) * 0.5;
        const x = cx + radius * Math.cos(-midAngle * RADIAN);
        const y = cy + radius * Math.sin(-midAngle * RADIAN);

        return (
            <text 
                x={x} 
                y={y} 
                fill="white" 
                textAnchor="middle" 
                dominantBaseline="central"
                fontSize="12"
                fontWeight="bold"
            >
                {`${(percent * 100).toFixed(0)}%`}
            </text>
        );
    };
    
    // Add total to each data point for percentage calculation
    const total = data.reduce((sum, item) => sum + item.value, 0);
    const dataWithTotal = data.map(item => ({ ...item, total }));
    
    return (
        <div className="bg-gray-50 rounded-lg p-4">
            <h4 className="font-medium text-gray-700 mb-4 text-center">Asset Allocation</h4>
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={dataWithTotal}
                            cx="50%"
                            cy="50%"
                            labelLine={false}
                            label={renderCustomizedLabel}
                            innerRadius={40}
                            outerRadius={80}
                            paddingAngle={2}
                            dataKey="value"
                        >
                            {dataWithTotal.map((entry, index) => (
                                <Cell key={`cell-${index}`} fill={entry.color} />
                            ))}
                        </Pie>
                        <Tooltip content={<CustomTooltip />} />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <CustomLegend payload={dataWithTotal} />
            <div className="mt-4 pt-3 border-t border-gray-200">
                <div className="flex justify-between text-sm font-medium">
                    <span className="text-gray-600">Total:</span>
                    <span className="text-gray-900">{formatINR(total)}</span>
                </div>
            </div>
        </div>
    );
};

export default AssetAllocationChart;