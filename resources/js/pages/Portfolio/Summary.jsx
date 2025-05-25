import React, { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout'; // Assuming you have a main app layout
import { Head } from '@inertiajs/react';
// Potentially import Heading or other UI components from '@/components/ui/...'
// For now, we'll use standard h1, h2, p tags.
// Assuming axios is globally available or imported via a helper
import axios from 'axios';


export default function PortfolioSummary() {
    const [summaryMetrics, setSummaryMetrics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        // Ensure the route name 'api.portfolio.summary' matches what was defined in web.php
        // If Ziggy's route() is not available, this would be a direct path: '/api/portfolio/summary-metrics'
        axios.get(route('api.portfolio.summary'))
            .then(response => {
                setSummaryMetrics(response.data);
                setLoading(false);
            })
            .catch(err => {
                console.error("Error fetching summary metrics:", err);
                let errorMessage = 'Failed to load summary metrics.';
                if (err.response && err.response.status === 401) {
                    errorMessage = 'Unauthorized. Please log in again.';
                } else if (err.response && err.response.data && err.response.data.message) {
                    errorMessage = err.response.data.message;
                }
                setError(errorMessage);
                setLoading(false);
            });
    }, []);

    // Helper function to format currency
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
    };

    // Helper function to format percentage
    const formatPercentage = (value) => {
        return `${parseFloat(value).toFixed(2)}%`;
    };

    if (loading) {
        return (
            <AppLayout>
                <Head title="Portfolio Summary" />
                <div className="container mx-auto px-4 py-8">
                    <p>Loading summary...</p>
                </div>
            </AppLayout>
        );
    }

    if (error) {
        return (
            <AppLayout>
                <Head title="Portfolio Summary" />
                <div className="container mx-auto px-4 py-8">
                    <p className="text-red-500">{error}</p>
                </div>
            </AppLayout>
        );
    }

    if (!summaryMetrics) {
        return (
            <AppLayout>
                <Head title="Portfolio Summary" />
                <div className="container mx-auto px-4 py-8">
                    <p>No summary data available.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="Portfolio Summary" />
            <div className="container mx-auto px-4 py-8">
                <div className="space-y-6">
                    <h1 className="text-3xl font-bold text-gray-800 mb-6">Portfolio Summary</h1>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white shadow-lg rounded-lg p-6">
                            <h2 className="text-xl font-semibold text-gray-700 mb-2">Total Principal Invested:</h2>
                            <p className="text-2xl text-blue-600">{formatCurrency(summaryMetrics.total_principal_invested)}</p>
                        </div>
                        <div className="bg-white shadow-lg rounded-lg p-6">
                            <h2 className="text-xl font-semibold text-gray-700 mb-2">Total Maturity Value:</h2>
                            <p className="text-2xl text-green-600">{formatCurrency(summaryMetrics.total_maturity_value)}</p>
                        </div>
                        <div className="bg-white shadow-lg rounded-lg p-6">
                            <h2 className="text-xl font-semibold text-gray-700 mb-2">Total Interest Earned:</h2>
                            <p className="text-2xl text-purple-600">{formatCurrency(summaryMetrics.total_interest_earned)}</p>
                        </div>
                        <div className="bg-white shadow-lg rounded-lg p-6">
                            <h2 className="text-xl font-semibold text-gray-700 mb-2">Weighted Average Interest Rate:</h2>
                            <p className="text-2xl text-teal-600">{formatPercentage(summaryMetrics.weighted_average_interest_rate)}</p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
