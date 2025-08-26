import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { Plus, TrendingUp, TrendingDown, Search, ChevronDown, ChevronRight } from 'lucide-react';

// Error Boundary Component
class ErrorBoundary extends React.Component {
    constructor(props: any) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: any) {
        return { hasError: true, error };
    }

    componentDidCatch(error: any, errorInfo: any) {
        console.error('Error caught by boundary:', error, errorInfo);
    }

    render() {
        if ((this.state as any).hasError) {
            return (
                <div className="p-4 border border-red-300 bg-red-50 rounded-lg">
                    <h2 className="text-red-800 font-semibold">Something went wrong</h2>
                    <p className="text-red-600 text-sm mt-2">
                        Error: {(this.state as any).error?.message || 'Unknown error'}
                    </p>
                    <Button 
                        onClick={() => this.setState({ hasError: false, error: null })}
                        className="mt-3"
                        size="sm"
                    >
                        Try Again
                    </Button>
                </div>
            );
        }

        return (this.props as any).children;
    }
}

function formatINR(amount: number) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
}

function formatPercentage(value: number) {
    return `${value.toFixed(2)}%`;
}

function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('en-IN');
}

interface MutualFund {
    mutual_fund_id: number;
    scheme_code: string;
    scheme_name: string;
    fund_house: string;
    category: string;
    current_nav: number;
    nav_date: string;
    total_units: number;
    avg_nav: number;
    total_investment: number;
    current_value: number;
    total_pl: number;
    total_pl_percent: number;
    transactions: Transaction[];
}

interface Transaction {
    id: number;
    transaction_type: string;
    units: number;
    nav: number;
    amount: number;
    net_amount: number;
    transaction_date: string;
    folio_number: string;
    days_held: number;
    notes: string;
}

interface PortfolioMetrics {
    total_investment: number;
    total_current_value: number;
    total_pl: number;
    total_pl_percent: number;
    best_performer: MutualFund | null;
    worst_performer: MutualFund | null;
    fund_house_distribution: Record<string, number>;
    category_distribution: Record<string, number>;
    total_schemes: number;
}

interface Props {
    holdings: MutualFund[];
    portfolioMetrics: PortfolioMetrics;
}

function MutualFunds({ holdings, portfolioMetrics }: Props) {
    // Add error boundary wrapper
    const MutualFundsContent = () => {
    const [showAddModal, setShowAddModal] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [selectedFund, setSelectedFund] = useState<any>(null);
    const [isSearching, setIsSearching] = useState(false);
    const [expandedFunds, setExpandedFunds] = useState<Set<number>>(new Set());
    const [addUnitsDialogOpen, setAddUnitsDialogOpen] = useState(false);
    const [selectedFundForAddUnits, setSelectedFundForAddUnits] = useState<any>(null);
    const [formData, setFormData] = useState({
        mutual_fund_id: '',
        transaction_type: 'buy',
        units: '',
        nav: '',
        amount: '',
        transaction_date: new Date().toISOString().split('T')[0],
        folio_number: '',
        stamp_duty: '0',
        transaction_charges: '0',
        gst: '0',
        net_amount: '',
        order_id: '',
        notes: ''
    });

    const searchFunds = async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        try {
            const response = await fetch(`/mutual-funds/search-funds?query=${encodeURIComponent(query)}`);
            const data = await response.json();
            setSearchResults(data);
        } catch (error) {
            console.error('Error searching funds:', error);
        }
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        searchFunds(value);
    };

    const selectFund = (fund: any) => {
        setSelectedFund(fund);
        setFormData(prev => ({
            ...prev,
            mutual_fund_id: fund.id.toString(),
            nav: fund.current_nav?.toString() || ''
        }));
        setSearchQuery(fund.scheme_name);
        setSearchResults([]);
    };

    const resetForm = () => {
        setFormData({
            mutual_fund_id: '',
            transaction_type: 'buy',
            units: '',
            nav: '',
            amount: '',
            transaction_date: new Date().toISOString().split('T')[0],
            folio_number: '',
            stamp_duty: '0',
            transaction_charges: '0',
            gst: '0',
            net_amount: '',
            order_id: '',
            notes: ''
        });
        setSelectedFund(null);
        setSearchQuery('');
        setSearchResults([]);
    };

    const toggleFundExpansion = (fundId: number) => {
        // Ensure fundId is a number to maintain Set consistency
        const numericFundId = Number(fundId);
        
        setExpandedFunds(prev => {
            const newSet = new Set(prev);
            if (newSet.has(numericFundId)) {
                newSet.delete(numericFundId);
            } else {
                newSet.add(numericFundId);
            }
            return newSet;
        });
    };

    const handleAddUnits = (fund: any) => {
        setSelectedFundForAddUnits(fund);
        setFormData(prev => ({
            ...prev,
            mutual_fund_id: fund.mutual_fund_id.toString(),
            transaction_type: 'buy'
        }));
        setSelectedFund({
            id: fund.mutual_fund_id,
            scheme_name: fund.scheme_name,
            fund_house: fund.fund_house,
            current_nav: fund.current_nav
        });
        setAddUnitsDialogOpen(true);
    };

    useEffect(() => {
        const amount = parseFloat(formData.amount) || 0;
        const stampDuty = parseFloat(formData.stamp_duty) || 0;
        const transactionCharges = parseFloat(formData.transaction_charges) || 0;
        const gst = parseFloat(formData.gst) || 0;
        
        const netAmount = formData.transaction_type === 'sell' 
            ? amount - stampDuty - transactionCharges - gst
            : amount + stampDuty + transactionCharges + gst;
        
        setFormData(prev => ({ ...prev, net_amount: netAmount.toFixed(2) }));
    }, [formData.amount, formData.stamp_duty, formData.transaction_charges, formData.gst, formData.transaction_type]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Debug: Log form data before submission
        console.log('Submitting form data:', formData);
        
        router.post('/mutual-funds', formData, {
            onSuccess: () => {
                console.log('Transaction added successfully');
                resetForm();
                setAddUnitsDialogOpen(false);
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
            onFinish: () => {
                console.log('Request finished');
            }
        });
    };

        return (
            <div className="p-4">
                <Head title="Mutual Funds" />
            
            {/* Action buttons at the top */}
            <div className="mb-4 flex items-center justify-between">
                <div className="flex gap-3">
                    <Dialog open={showAddModal} onOpenChange={setShowAddModal}>
                        <DialogTrigger asChild>
                            <Button variant="default">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Transaction
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Add Mutual Fund Transaction</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="col-span-2">
                                        <Label htmlFor="fund-search">Search Mutual Fund</Label>
                                        <div className="relative">
                                            <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="fund-search"
                                                placeholder="Search by scheme name, code, or fund house..."
                                                value={searchQuery}
                                                onChange={(e) => handleSearchChange(e.target.value)}
                                                className="pl-10"
                                            />
                                            {searchResults.length > 0 && (
                                                <div className="absolute z-10 w-full mt-1 bg-white border rounded-md shadow-lg max-h-60 overflow-auto">
                                                    {searchResults.map((fund: any) => (
                                                        <div
                                                            key={fund.id}
                                                            className="p-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0"
                                                            onClick={() => selectFund(fund)}
                                                        >
                                                            <div className="font-medium">{fund.scheme_name}</div>
                                                            <div className="text-sm text-gray-500">
                                                                {fund.fund_house} • {fund.scheme_code}
                                                                {fund.current_nav && (
                                                                    <span className="ml-2">NAV: ₹{fund.current_nav}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="transaction_type">Transaction Type</Label>
                                        <Select value={formData.transaction_type} onValueChange={(value) => setFormData(prev => ({ ...prev, transaction_type: value }))}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="buy">Buy</SelectItem>
                                                <SelectItem value="sell">Sell</SelectItem>
                                                <SelectItem value="sip">SIP</SelectItem>
                                                <SelectItem value="dividend_reinvestment">Dividend Reinvestment</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="transaction_date">Transaction Date</Label>
                                        <Input
                                            id="transaction_date"
                                            type="date"
                                            value={formData.transaction_date}
                                            onChange={(e) => setFormData(prev => ({ ...prev, transaction_date: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="units">Units</Label>
                                        <Input
                                            id="units"
                                            type="number"
                                            step="0.000001"
                                            value={formData.units}
                                            onChange={(e) => setFormData(prev => ({ ...prev, units: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="nav">NAV</Label>
                                        <Input
                                            id="nav"
                                            type="number"
                                            step="0.0001"
                                            value={formData.nav}
                                            onChange={(e) => setFormData(prev => ({ ...prev, nav: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="amount">Amount</Label>
                                        <Input
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            value={formData.amount}
                                            onChange={(e) => setFormData(prev => ({ ...prev, amount: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="folio_number">Folio Number</Label>
                                        <Input
                                            id="folio_number"
                                            type="text"
                                            value={formData.folio_number}
                                            onChange={(e) => setFormData(prev => ({ ...prev, folio_number: e.target.value }))}
                                            placeholder="Optional"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="stamp_duty">Stamp Duty</Label>
                                        <Input
                                            id="stamp_duty"
                                            type="number"
                                            step="0.01"
                                            value={formData.stamp_duty}
                                            onChange={(e) => setFormData(prev => ({ ...prev, stamp_duty: e.target.value }))}
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="transaction_charges">Transaction Charges</Label>
                                        <Input
                                            id="transaction_charges"
                                            type="number"
                                            step="0.01"
                                            value={formData.transaction_charges}
                                            onChange={(e) => setFormData(prev => ({ ...prev, transaction_charges: e.target.value }))}
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="gst">GST</Label>
                                        <Input
                                            id="gst"
                                            type="number"
                                            step="0.01"
                                            value={formData.gst}
                                            onChange={(e) => setFormData(prev => ({ ...prev, gst: e.target.value }))}
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="net_amount">Net Amount</Label>
                                        <Input
                                            id="net_amount"
                                            type="number"
                                            step="0.01"
                                            value={formData.net_amount}
                                            onChange={(e) => setFormData(prev => ({ ...prev, net_amount: e.target.value }))}
                                            required
                                            readOnly
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="order_id">Order ID</Label>
                                        <Input
                                            id="order_id"
                                            type="text"
                                            value={formData.order_id}
                                            onChange={(e) => setFormData(prev => ({ ...prev, order_id: e.target.value }))}
                                            placeholder="Optional"
                                        />
                                    </div>
                                    
                                    <div className="col-span-2">
                                        <Label htmlFor="notes">Notes</Label>
                                        <Input
                                            id="notes"
                                            type="text"
                                            value={formData.notes}
                                            onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
                                            placeholder="Optional notes"
                                        />
                                    </div>
                                </div>
                                
                                <div className="flex justify-end gap-3">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={!selectedFund}>
                                        Add Transaction
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                    
                    {/* Add Units Dialog */}
                    <Dialog open={addUnitsDialogOpen} onOpenChange={setAddUnitsDialogOpen}>
                        <DialogContent className="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Add Units - {selectedFundForAddUnits?.scheme_name}</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="add_units">Units</Label>
                                        <Input
                                            id="add_units"
                                            type="number"
                                            step="0.001"
                                            value={formData.units}
                                            onChange={(e) => setFormData(prev => ({ ...prev, units: e.target.value }))}
                                            required
                                            placeholder="Enter number of units"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="add_nav">NAV</Label>
                                        <Input
                                            id="add_nav"
                                            type="number"
                                            step="0.01"
                                            value={formData.nav}
                                            onChange={(e) => setFormData(prev => ({ ...prev, nav: e.target.value }))}
                                            required
                                            placeholder="Net Asset Value"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="add_amount">Amount</Label>
                                        <Input
                                            id="add_amount"
                                            type="number"
                                            step="0.01"
                                            value={formData.amount}
                                            onChange={(e) => setFormData(prev => ({ ...prev, amount: e.target.value }))}
                                            required
                                            placeholder="Transaction amount"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="add_transaction_date">Transaction Date</Label>
                                        <Input
                                            id="add_transaction_date"
                                            type="date"
                                            value={formData.transaction_date}
                                            onChange={(e) => setFormData(prev => ({ ...prev, transaction_date: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="add_folio_number">Folio Number</Label>
                                        <Input
                                            id="add_folio_number"
                                            type="text"
                                            value={formData.folio_number}
                                            onChange={(e) => setFormData(prev => ({ ...prev, folio_number: e.target.value }))}
                                            placeholder="Optional"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="add_net_amount">Net Amount</Label>
                                        <Input
                                            id="add_net_amount"
                                            type="number"
                                            step="0.01"
                                            value={formData.net_amount}
                                            onChange={(e) => setFormData(prev => ({ ...prev, net_amount: e.target.value }))}
                                            required
                                            readOnly
                                        />
                                    </div>
                                </div>
                                
                                <div className="flex justify-end gap-3">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline" onClick={() => {
                                            setAddUnitsDialogOpen(false);
                                            resetForm();
                                        }}>
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit">
                                        Add Units
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                    
                    <Button variant="outline">
                        <TrendingUp className="mr-2 h-4 w-4" />
                        Sync NAV
                    </Button>
                </div>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Investment</CardTitle>
                        <TrendingUp className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{formatINR(portfolioMetrics.total_investment)}</div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Current Value</CardTitle>
                        <TrendingUp className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{formatINR(portfolioMetrics.total_current_value)}</div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total P&L</CardTitle>
                        {portfolioMetrics.total_pl >= 0 ? (
                            <TrendingUp className="h-4 w-4 text-green-600" />
                        ) : (
                            <TrendingDown className="h-4 w-4 text-red-600" />
                        )}
                    </CardHeader>
                    <CardContent>
                        <div className={`text-2xl font-bold ${
                            portfolioMetrics.total_pl >= 0 ? 'text-green-600' : 'text-red-600'
                        }`}>
                            {formatINR(portfolioMetrics.total_pl)}
                        </div>
                        <p className={`text-xs ${
                            portfolioMetrics.total_pl_percent >= 0 ? 'text-green-600' : 'text-red-600'
                        }`}>
                            {formatPercentage(portfolioMetrics.total_pl_percent)}
                        </p>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Schemes</CardTitle>
                        <TrendingUp className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{portfolioMetrics.total_schemes}</div>
                    </CardContent>
                </Card>
            </div>

            {/* Holdings Table */}
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Mutual Fund Holdings</CardTitle>
                </CardHeader>
                <CardContent>
                    {holdings.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            No mutual fund holdings found. Add your first transaction to get started.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {holdings.filter(fund => fund && fund.mutual_fund_id).map((fund) => {
                                const numericFundId = Number(fund.mutual_fund_id);
                                const isExpanded = expandedFunds.has(numericFundId);
                                const buyTransactions = (fund.transactions || []).filter((t: any) => t && t.transaction_type && (t.transaction_type === 'buy' || t.transaction_type === 'sip'));
                                const hasMultipleTransactions = buyTransactions.length > 2;
                                
                                return (
                                    <div key={fund.mutual_fund_id} className="border rounded-lg p-4">
                                        <div className="flex justify-between items-start">
                                            <div className="flex-1">
                                                <h3 className="font-semibold">{fund.scheme_name}</h3>
                                                <p className="text-sm text-muted-foreground">{fund.fund_house}</p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="text-right">
                                                    <div className="text-lg font-semibold">{formatINR(fund.current_value)}</div>
                                                    <div className={`text-sm ${
                                                        fund.total_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                                    }`}>
                                                        {formatINR(fund.total_pl)} ({formatPercentage(fund.total_pl_percent)})
                                                    </div>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    onClick={() => handleAddUnits(fund)}
                                                    className="ml-2"
                                                >
                                                    <Plus className="h-4 w-4 mr-1" />
                                                    Add Units
                                                </Button>
                                            </div>
                                        </div>
                                        
                                        <div className="grid grid-cols-4 gap-4 mt-4 text-sm">
                                            <div>
                                                <span className="text-muted-foreground">Units:</span>
                                                <div className="font-medium">{fund.total_units.toFixed(3)}</div>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Avg NAV:</span>
                                                <div className="font-medium">{formatINR(fund.avg_nav)}</div>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Current NAV:</span>
                                                <div className="font-medium">{formatINR(fund.current_nav)}</div>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Investment:</span>
                                                <div className="font-medium">{formatINR(fund.total_investment)}</div>
                                            </div>
                                        </div>
                                        
                                        {hasMultipleTransactions && (
                                            <div className="mt-4">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        console.log('Button clicked for fund:', numericFundId);
                                                        toggleFundExpansion(numericFundId);
                                                    }}
                                                    className="flex items-center gap-2"
                                                >
                                                    {isExpanded ? (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4" />
                                                    )}
                                                    {isExpanded ? 'Hide' : 'Show'} Transaction Details ({buyTransactions.length} purchases)
                                                </Button>
                                                
                                                {isExpanded && (
                                                    <div className="mt-3 space-y-2">
                                                        {buyTransactions.filter((transaction: any) => transaction && transaction.id).map((transaction: any) => (
                                                            <div key={transaction.id} className="bg-gray-50 rounded-lg p-3 text-sm">
                                                                <div className="flex justify-between items-start">
                                                                    <div className="flex-1">
                                                                        <div className="flex items-center gap-2 mb-2">
                                                                            <Badge variant={(transaction.days_held || 0) > 365 ? 'default' : 'secondary'}>
                                                                                {(transaction.days_held || 0) > 365 ? 'LTCG' : 'STCG'}
                                                                            </Badge>
                                                                            <span className="text-muted-foreground">
                                                                                {transaction.transaction_date ? new Date(transaction.transaction_date).toLocaleDateString() : 'N/A'}
                                                                            </span>
                                                                        </div>
                                                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                                            <div>
                                                                <span className="text-muted-foreground">Units:</span>
                                                                <div className="font-medium">{Number(transaction.units || 0).toFixed(3)}</div>
                                                            </div>
                                                                            <div>
                                                                                <span className="text-muted-foreground">NAV:</span>
                                                                                <div className="font-medium">{formatINR(transaction.nav || 0)}</div>
                                                                            </div>
                                                                            <div>
                                                                                <span className="text-muted-foreground">Amount:</span>
                                                                                <div className="font-medium">{formatINR(transaction.amount || 0)}</div>
                                                                            </div>
                                                                            <div>
                                                                                <span className="text-muted-foreground">Net Amount:</span>
                                                                                <div className="font-medium">{formatINR(transaction.net_amount || 0)}</div>
                                                                            </div>
                                                                        </div>
                                                                        {transaction.folio_number && (
                                                                            <div className="mt-2">
                                                                                <span className="text-muted-foreground">Folio:</span>
                                                                                <span className="ml-1 font-medium">{transaction.folio_number}</span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
            </div>
        );
    };

    return (
        <ErrorBoundary>
            <MutualFundsContent />
        </ErrorBoundary>
    );
}

MutualFunds.layout = (page: any) => <AppSidebarLayout breadcrumbs={[{ title: 'Mutual Funds', href: '/mutual-funds' }]} children={page} />;

export default MutualFunds;