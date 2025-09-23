import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { Plus, TrendingUp, TrendingDown, Search, ChevronDown, ChevronRight, Edit, Trash2, Minus } from 'lucide-react';

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

function formatINR(amount: number | null) {
    if (amount === null || amount === undefined || isNaN(amount)) {
        return '-';
    }
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
    const [editTransactionDialogOpen, setEditTransactionDialogOpen] = useState(false);
    const [selectedTransaction, setSelectedTransaction] = useState<any>(null);
    const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
    const [transactionToDelete, setTransactionToDelete] = useState<any>(null);
    const [isSyncingNavs, setIsSyncingNavs] = useState(false);
    const [showAddFundModal, setShowAddFundModal] = useState(false);
    const [newFundData, setNewFundData] = useState({
        scheme_name: '',
        scheme_code: '',
        fund_house: '',
        category: '',
        sub_category: '',
        current_nav: ''
    });
    const [newFundSearchResults, setNewFundSearchResults] = useState([]);
    const [isSearchingNewFund, setIsSearchingNewFund] = useState(false);
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
    const [showSoldHistory, setShowSoldHistory] = useState(false);
    const [soldHistory, setSoldHistory] = useState<any[]>([]);
    const [redeemDialogOpen, setRedeemDialogOpen] = useState(false);
    const [selectedFundForRedeem, setSelectedFundForRedeem] = useState<any>(null);
    const [redeemFormData, setRedeemFormData] = useState({
        units: '',
        redemption_type: 'partial', // 'partial' or 'full'
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

    // Fetch sold history when showSoldHistory is toggled
    useEffect(() => {
        if (showSoldHistory) {
            fetchSoldHistory();
        }
    }, [showSoldHistory]);

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

    const fetchSoldHistory = async () => {
        try {
            const response = await fetch('/mutual-funds/sold-history');
            if (response.ok) {
                const data = await response.json();
                setSoldHistory(data);
            } else {
                console.error('Failed to fetch sold history');
            }
        } catch (error) {
            console.error('Error fetching sold history:', error);
        }
    };

    const handleRedeem = (fund: any) => {
        setSelectedFundForRedeem(fund);
        setRedeemFormData(prev => ({
            ...prev,
            nav: fund.current_nav.toString(),
            folio_number: fund.transactions?.[0]?.folio_number || ''
        }));
        setRedeemDialogOpen(true);
    };

    // Calculate amount and net_amount when units or nav changes
    const calculateAmounts = (units: string, nav: string) => {
        const unitsNum = parseFloat(units) || 0;
        const navNum = parseFloat(nav) || 0;
        const amount = unitsNum * navNum;
        const stampDuty = parseFloat(redeemFormData.stamp_duty) || 0;
        const transactionCharges = parseFloat(redeemFormData.transaction_charges) || 0;
        const gst = parseFloat(redeemFormData.gst) || 0;
        const netAmount = amount - stampDuty - transactionCharges - gst;
        
        return {
            amount: amount.toFixed(2),
            net_amount: Math.max(0, netAmount).toFixed(2)
        };
    };

    const handleRedeemSubmit = async () => {
        try {
            const units = redeemFormData.redemption_type === 'full' ? selectedFundForRedeem.total_units.toString() : redeemFormData.units;
            const nav = redeemFormData.nav;
            const calculatedAmounts = calculateAmounts(units, nav);
            
            const response = await fetch('/mutual-funds', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    mutual_fund_id: selectedFundForRedeem.mutual_fund_id,
                    transaction_type: 'redemption',
                    units: parseFloat(units),
                    nav: parseFloat(nav),
                    amount: parseFloat(calculatedAmounts.amount),
                    transaction_date: redeemFormData.transaction_date,
                    folio_number: redeemFormData.folio_number,
                    stamp_duty: parseFloat(redeemFormData.stamp_duty) || 0,
                    transaction_charges: parseFloat(redeemFormData.transaction_charges) || 0,
                    gst: parseFloat(redeemFormData.gst) || 0,
                    net_amount: parseFloat(calculatedAmounts.net_amount),
                    order_id: redeemFormData.order_id,
                    notes: redeemFormData.notes
                })
            });

            if (response.ok) {
                setRedeemDialogOpen(false);
                window.location.reload(); // Refresh to show updated data
            } else {
                alert('Failed to process redemption');
            }
        } catch (error) {
            console.error('Error processing redemption:', error);
            alert('Error processing redemption');
        }
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

    const handleEditTransaction = (transaction: any) => {
        // Create a copy of the transaction with proper date formatting
        const transactionCopy = {
            ...transaction,
            transaction_date: transaction.transaction_date ? transaction.transaction_date.split('T')[0] : '',
            units: transaction.units.toString(),
            nav: transaction.nav.toString(),
            amount: transaction.amount.toString(),
            net_amount: transaction.net_amount.toString(),
            stamp_duty: transaction.stamp_duty?.toString() || '0',
            transaction_charges: transaction.transaction_charges?.toString() || '0',
            gst: transaction.gst?.toString() || '0'
        };
        setSelectedTransaction(transactionCopy);
        setEditTransactionDialogOpen(true);
    };

    const handleDeleteTransaction = (transaction: any) => {
        setTransactionToDelete(transaction);
        setDeleteConfirmOpen(true);
    };

    const confirmDeleteTransaction = () => {
        if (transactionToDelete) {
            router.delete(`/mutual-funds/transactions/${transactionToDelete.id}`, {
                onSuccess: () => {
                    setDeleteConfirmOpen(false);
                    setTransactionToDelete(null);
                    // Refresh the page to reload data
                    router.reload();
                },
                onError: (errors) => {
                    console.error('Error deleting transaction:', errors);
                }
            });
        }
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (selectedTransaction) {
            router.put(`/mutual-funds/transactions/${selectedTransaction.id}`, selectedTransaction, {
                onSuccess: () => {
                    setEditTransactionDialogOpen(false);
                    setSelectedTransaction(null);
                    // Refresh the page to reload data
                    router.reload();
                },
                onError: (errors) => {
                    console.error('Error updating transaction:', errors);
                }
            });
        }
    };

    const syncNavs = async () => {
        setIsSyncingNavs(true);
        try {
            const response = await fetch('/mutual-funds/sync-navs', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Refresh the page to show updated NAVs
                router.reload();
            } else {
                console.error('Error syncing NAVs:', data.message);
            }
        } catch (error) {
            console.error('Error syncing NAVs:', error);
        } finally {
            setIsSyncingNavs(false);
        }
    };

    const searchNewFund = async (query: string) => {
        if (!query.trim()) {
            setNewFundSearchResults([]);
            return;
        }

        setIsSearchingNewFund(true);
        try {
            const response = await fetch(`/mutual-funds/search-funds?query=${encodeURIComponent(query)}`);
            const data = await response.json();
            setNewFundSearchResults(data);
        } catch (error) {
            console.error('Error searching funds for auto-populate:', error);
        } finally {
            setIsSearchingNewFund(false);
        }
    };

    const handleNewFundSchemeNameChange = (value: string) => {
        setNewFundData(prev => ({ ...prev, scheme_name: value }));
        searchNewFund(value);
    };

    const selectExistingFundForPopulate = (fund: any) => {
        setNewFundData({
            scheme_name: fund.scheme_name,
            scheme_code: fund.scheme_code,
            fund_house: fund.fund_house,
            category: '', // These fields are not returned by search API
            sub_category: '',
            current_nav: fund.current_nav?.toString() || ''
        });
        setNewFundSearchResults([]);
    };

    const addNewFund = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            const response = await fetch('/mutual-funds/add-fund', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(newFundData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Reset form and close modal
                setNewFundData({
                    scheme_name: '',
                    scheme_code: '',
                    fund_house: '',
                    category: '',
                    sub_category: '',
                    current_nav: ''
                });
                setNewFundSearchResults([]);
                setShowAddFundModal(false);
                // Refresh search results
                if (searchQuery) {
                    searchFunds(searchQuery);
                }
            } else {
                console.error('Error adding fund:', data.message);
            }
        } catch (error) {
            console.error('Error adding fund:', error);
        }
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

    // Recalculate net_amount for selectedTransaction when editing
    useEffect(() => {
        if (selectedTransaction) {
            const amount = parseFloat(selectedTransaction.amount) || 0;
            const stampDuty = parseFloat(selectedTransaction.stamp_duty) || 0;
            const transactionCharges = parseFloat(selectedTransaction.transaction_charges) || 0;
            const gst = parseFloat(selectedTransaction.gst) || 0;
            
            const netAmount = selectedTransaction.transaction_type === 'sell' 
                ? amount - stampDuty - transactionCharges - gst
                : amount + stampDuty + transactionCharges + gst;
            
            setSelectedTransaction((prev: any) => prev ? ({ ...prev, net_amount: netAmount.toFixed(2) }) : null);
        }
    }, [selectedTransaction?.amount, selectedTransaction?.stamp_duty, selectedTransaction?.transaction_charges, selectedTransaction?.gst, selectedTransaction?.transaction_type]);

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
                                        <div className="flex gap-2">
                                            <div className="relative flex-1">
                                                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                                <Input
                                                    id="fund-search"
                                                    placeholder="Search by scheme name, code, or fund house..."
                                                    value={searchQuery}
                                                    onChange={(e) => handleSearchChange(e.target.value)}
                                                    className="pl-10"
                                                />
                                            </div>
                                            <Dialog open={showAddFundModal} onOpenChange={setShowAddFundModal}>
                                                <DialogTrigger asChild>
                                                    <Button variant="outline" size="icon" className="bg-black text-white hover:bg-gray-800">
                                                        <Plus className="h-4 w-4" />
                                                    </Button>
                                                </DialogTrigger>
                                            </Dialog>
                                        </div>
                                        <div className="relative">
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
                    
                    {/* Add Fund Dialog */}
                    <Dialog open={showAddFundModal} onOpenChange={setShowAddFundModal}>
                        <DialogContent className="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Add New Mutual Fund</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={addNewFund} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="col-span-2">
                                        <Label htmlFor="scheme_name">Scheme Name</Label>
                                        <div className="flex gap-2">
                                            <div className="relative flex-1">
                                                <Input
                                                    id="scheme_name"
                                                    type="text"
                                                    value={newFundData.scheme_name}
                                                    onChange={(e) => setNewFundData(prev => ({ ...prev, scheme_name: e.target.value }))}
                                                    required
                                                    placeholder="e.g., HDFC Flexi Cap Direct Plan Growth"
                                                />
                                                {newFundSearchResults.length > 0 && (
                                                    <div className="absolute z-10 w-full mt-1 bg-white border rounded-md shadow-lg max-h-60 overflow-auto">
                                                        <div className="p-2 text-xs text-gray-500 border-b bg-gray-50">
                                                            💡 Found existing funds - click to auto-populate fields:
                                                        </div>
                                                        {newFundSearchResults.map((fund: any) => (
                                                            <div
                                                                key={fund.id}
                                                                className="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-b-0"
                                                                onClick={() => selectExistingFundForPopulate(fund)}
                                                            >
                                                                <div className="font-medium text-blue-600">{fund.scheme_name}</div>
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
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => searchNewFund(newFundData.scheme_name)}
                                                disabled={!newFundData.scheme_name.trim() || isSearchingNewFund}
                                                className="px-3"
                                            >
                                                {isSearchingNewFund ? (
                                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                                ) : (
                                                    "Sync"
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="scheme_code">Scheme Code</Label>
                                        <Input
                                            id="scheme_code"
                                            type="text"
                                            value={newFundData.scheme_code}
                                            onChange={(e) => setNewFundData(prev => ({ ...prev, scheme_code: e.target.value }))}
                                            required
                                            placeholder="e.g., 120503"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="fund_house">Fund House</Label>
                                        <Input
                                            id="fund_house"
                                            type="text"
                                            value={newFundData.fund_house}
                                            onChange={(e) => setNewFundData(prev => ({ ...prev, fund_house: e.target.value }))}
                                            required
                                            placeholder="e.g., HDFC Mutual Fund"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="category">Category</Label>
                                        <Input
                                            id="category"
                                            type="text"
                                            value={newFundData.category}
                                            onChange={(e) => setNewFundData(prev => ({ ...prev, category: e.target.value }))}
                                            required
                                            placeholder="e.g., Equity"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="sub_category">Sub Category</Label>
                                        <Input
                                            id="sub_category"
                                            type="text"
                                            value={newFundData.sub_category}
                                            onChange={(e) => setNewFundData(prev => ({ ...prev, sub_category: e.target.value }))}
                                            required
                                            placeholder="e.g., Flexi Cap Fund"
                                        />
                                    </div>
                                    
                                    <div>
                                        <Label htmlFor="current_nav">Current NAV</Label>
                                        <Input
                                            id="current_nav"
                                            type="number"
                                            step="0.0001"
                                            value={newFundData.current_nav}
                                            onChange={(e) => setNewFundData(prev => ({ ...prev, current_nav: e.target.value }))}
                                            placeholder="e.g., 850.25"
                                        />
                                    </div>
                                </div>
                                
                                <div className="flex justify-end gap-3">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit">
                                        Add Fund
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                    
                    <Button variant="outline" onClick={syncNavs} disabled={isSyncingNavs}>
                        <TrendingUp className="mr-2 h-4 w-4" />
                        {isSyncingNavs ? 'Syncing...' : 'Sync NAV'}
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

            {/* Show Sold History Checkbox */}
            <div className="mt-6 flex items-center space-x-2">
                <Checkbox 
                    id="show-sold-history" 
                    checked={showSoldHistory}
                    onCheckedChange={(checked) => setShowSoldHistory(checked === true)}
                />
                <Label htmlFor="show-sold-history">Show Sold History</Label>
            </div>

            {/* Sold History Table */}
            {showSoldHistory && (
                <Card className="mt-4">
                    <CardHeader>
                        <CardTitle>Sold History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {soldHistory.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                No sold transactions found.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Fund Name</th>
                                            <th className="text-left p-2">Units Sold</th>
                                            <th className="text-left p-2">Avg Buy NAV</th>
                                            <th className="text-left p-2">Sell NAV</th>
                                            <th className="text-left p-2">Investment</th>
                                            <th className="text-left p-2">Proceeds</th>
                                            <th className="text-left p-2">Realized P&L</th>
                                            <th className="text-left p-2">Sell Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {soldHistory.map((item: any, index: number) => (
                                            <tr key={index} className="border-b">
                                                <td className="p-2">{item.scheme_name}</td>
                                                <td className="p-2">{item.units_sold || '-'}</td>
                                                <td className="p-2">{formatINR(item.avg_buy_nav)}</td>
                                                <td className="p-2">{formatINR(item.sell_nav)}</td>
                                                <td className="p-2">{formatINR(item.total_investment)}</td>
                                                <td className="p-2">{formatINR(item.total_proceeds)}</td>
                                                <td className={`p-2 ${(item.realized_pl || 0) >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                    {formatINR(item.realized_pl)}
                                                </td>
                                                <td className="p-2">{item.sell_date ? new Date(item.sell_date).toLocaleDateString() : '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

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
                                const hasMultipleTransactions = buyTransactions.length >= 1;
                                
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
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleRedeem(fund)}
                                                    className="ml-2"
                                                >
                                                    <Minus className="h-4 w-4 mr-1" />
                                                    Redeem
                                                </Button>
                                            </div>
                                        </div>
                                        
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
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
                                                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-2">
                                                                            <div>
                                                                                <span className="text-muted-foreground">Type/Date:</span>
                                                                                <div className="flex items-center gap-2 mt-1">
                                                                                    <Badge className={`text-xs ${
                                                                                        (transaction.days_held || 0) > 365 
                                                                                            ? 'bg-green-100 text-green-800 border-green-300' 
                                                                                            : 'bg-orange-100 text-orange-800 border-orange-300'
                                                                                    }`}>
                                                                                        {(transaction.days_held || 0) > 365 ? 'LTCG' : 'STCG'}
                                                                                    </Badge>
                                                                                    <span className="text-xs text-muted-foreground">
                                                                                        {transaction.transaction_date ? new Date(transaction.transaction_date).toLocaleDateString() : 'N/A'}
                                                                                    </span>
                                                                                </div>
                                                                            </div>
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
                                                            <div>
                                                                <span className="text-muted-foreground">Current NAV:</span>
                                                                <div className="font-medium">{formatINR(fund.current_nav || 0)}</div>
                                                            </div>
                                                            <div>
                                                                <span className="text-muted-foreground">P/L:</span>
                                                                <div className={`font-medium ${
                                                                    ((fund.current_nav || 0) - (transaction.nav || 0)) < 0 
                                                                        ? 'text-red-600' 
                                                                        : 'text-green-600'
                                                                }`}>
                                                                    {formatINR((fund.current_nav || 0) - (transaction.nav || 0))}
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <span className="text-muted-foreground">Actions:</span>
                                                                                <div className="flex items-center gap-1 mt-1">
                                                                                    <Button
                                                                                        size="sm"
                                                                                        variant="ghost"
                                                                                        className="h-6 w-6 p-0 hover:bg-blue-100"
                                                                                        onClick={() => handleEditTransaction(transaction)}
                                                                                    >
                                                                                        <Edit className="h-3 w-3 text-blue-600" />
                                                                                    </Button>
                                                                                    <Button
                                                                                        size="sm"
                                                                                        variant="ghost"
                                                                                        className="h-6 w-6 p-0 hover:bg-red-100"
                                                                                        onClick={() => handleDeleteTransaction(transaction)}
                                                                                    >
                                                                                        <Trash2 className="h-3 w-3 text-red-600" />
                                                                                    </Button>
                                                                                </div>
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

            {/* Edit Transaction Dialog */}
            <Dialog open={editTransactionDialogOpen} onOpenChange={setEditTransactionDialogOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit Transaction</DialogTitle>
                    </DialogHeader>
                    {selectedTransaction && (
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-units" className="text-right">Units</Label>
                                <Input
                                    id="edit-units"
                                    type="number"
                                    step="0.001"
                                    value={selectedTransaction.units || ''}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, units: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-nav" className="text-right">NAV</Label>
                                <Input
                                    id="edit-nav"
                                    type="number"
                                    step="0.01"
                                    value={selectedTransaction.nav || ''}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, nav: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-amount" className="text-right">Amount</Label>
                                <Input
                                    id="edit-amount"
                                    type="number"
                                    step="0.01"
                                    value={selectedTransaction.amount || ''}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, amount: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-date" className="text-right">Date</Label>
                                <Input
                                    id="edit-date"
                                    type="date"
                                    value={selectedTransaction.transaction_date || ''}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, transaction_date: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-stamp-duty" className="text-right">Stamp Duty</Label>
                                <Input
                                    id="edit-stamp-duty"
                                    type="number"
                                    step="0.01"
                                    value={selectedTransaction.stamp_duty || '0'}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, stamp_duty: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-transaction-charges" className="text-right">Transaction Charges</Label>
                                <Input
                                    id="edit-transaction-charges"
                                    type="number"
                                    step="0.01"
                                    value={selectedTransaction.transaction_charges || '0'}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, transaction_charges: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-gst" className="text-right">GST</Label>
                                <Input
                                    id="edit-gst"
                                    type="number"
                                    step="0.01"
                                    value={selectedTransaction.gst || '0'}
                                    onChange={(e) => setSelectedTransaction({...selectedTransaction, gst: e.target.value})}
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="edit-net-amount" className="text-right">Net Amount</Label>
                                <Input
                                    id="edit-net-amount"
                                    type="number"
                                    step="0.01"
                                    value={selectedTransaction.net_amount || ''}
                                    readOnly
                                    className="col-span-3 bg-gray-50"
                                />
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditTransactionDialogOpen(false)}>Cancel</Button>
                        <Button onClick={handleEditSubmit}>Save Changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Delete Transaction</DialogTitle>
                    </DialogHeader>
                    <div className="py-4">
                        <p>Are you sure you want to delete this transaction? This action cannot be undone.</p>
                        {transactionToDelete && (
                            <div className="mt-4 p-3 bg-gray-50 rounded-lg">
                                <p className="text-sm"><strong>Units:</strong> {Number(transactionToDelete.units || 0).toFixed(3)}</p>
                                <p className="text-sm"><strong>NAV:</strong> {formatINR(transactionToDelete.nav || 0)}</p>
                                <p className="text-sm"><strong>Amount:</strong> {formatINR(transactionToDelete.amount || 0)}</p>
                                <p className="text-sm"><strong>Date:</strong> {transactionToDelete.transaction_date ? new Date(transactionToDelete.transaction_date).toLocaleDateString() : 'N/A'}</p>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteConfirmOpen(false)}>Cancel</Button>
                        <Button variant="destructive" onClick={confirmDeleteTransaction}>Delete</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Redeem Dialog */}
            <Dialog open={redeemDialogOpen} onOpenChange={setRedeemDialogOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Redeem Units</DialogTitle>
                    </DialogHeader>
                    {selectedFundForRedeem && (
                        <div className="space-y-4">
                            <div className="p-3 bg-gray-50 rounded-lg">
                                <p className="font-medium">{selectedFundForRedeem.scheme_name}</p>
                                <p className="text-sm text-muted-foreground">Available Units: {selectedFundForRedeem.total_units?.toFixed(3)}</p>
                                <p className="text-sm text-muted-foreground">Current NAV: {formatINR(selectedFundForRedeem.current_nav)}</p>
                            </div>
                            
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="redemption-type" className="text-right">Type</Label>
                                <select
                                    id="redemption-type"
                                    value={redeemFormData.redemption_type}
                                    onChange={(e) => setRedeemFormData(prev => ({ ...prev, redemption_type: e.target.value }))}
                                    className="col-span-3 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="partial">Partial Redemption</option>
                                    <option value="full">Full Redemption</option>
                                </select>
                            </div>
                            
                            {redeemFormData.redemption_type === 'partial' && (
                                <div className="grid grid-cols-4 items-center gap-4">
                                    <Label htmlFor="redeem-units" className="text-right">Units</Label>
                                    <Input
                                        id="redeem-units"
                                        type="number"
                                        step="0.001"
                                        value={redeemFormData.units}
                                        onChange={(e) => {
                                            const newUnits = e.target.value;
                                            const calculatedAmounts = calculateAmounts(newUnits, redeemFormData.nav);
                                            setRedeemFormData(prev => ({ 
                                                ...prev, 
                                                units: newUnits,
                                                amount: calculatedAmounts.amount,
                                                net_amount: calculatedAmounts.net_amount
                                            }));
                                        }}
                                        className="col-span-3"
                                        max={selectedFundForRedeem.total_units}
                                    />
                                </div>
                            )}
                            
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="redeem-nav" className="text-right">NAV</Label>
                                <Input
                                    id="redeem-nav"
                                    type="number"
                                    step="0.01"
                                    value={redeemFormData.nav}
                                    onChange={(e) => {
                                        const newNav = e.target.value;
                                        const units = redeemFormData.redemption_type === 'full' ? selectedFundForRedeem.total_units.toString() : redeemFormData.units;
                                        const calculatedAmounts = calculateAmounts(units, newNav);
                                        setRedeemFormData(prev => ({ 
                                            ...prev, 
                                            nav: newNav,
                                            amount: calculatedAmounts.amount,
                                            net_amount: calculatedAmounts.net_amount
                                        }));
                                    }}
                                    className="col-span-3"
                                />
                            </div>
                            
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="redeem-date" className="text-right">Date</Label>
                                <Input
                                    id="redeem-date"
                                    type="date"
                                    value={redeemFormData.transaction_date}
                                    onChange={(e) => setRedeemFormData(prev => ({ ...prev, transaction_date: e.target.value }))}
                                    className="col-span-3"
                                />
                            </div>
                            
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="redeem-folio" className="text-right">Folio Number</Label>
                                <Input
                                    id="redeem-folio"
                                    type="text"
                                    value={redeemFormData.folio_number}
                                    onChange={(e) => setRedeemFormData(prev => ({ ...prev, folio_number: e.target.value }))}
                                    className="col-span-3"
                                />
                            </div>
                            
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-right">Amount</Label>
                                <div className="col-span-3 px-3 py-2 bg-gray-50 rounded-md text-sm">
                                    ₹{redeemFormData.amount || '0.00'}
                                </div>
                            </div>
                            
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-right">Net Amount</Label>
                                <div className="col-span-3 px-3 py-2 bg-gray-50 rounded-md text-sm font-medium">
                                    ₹{redeemFormData.net_amount || '0.00'}
                                </div>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRedeemDialogOpen(false)}>Cancel</Button>
                        <Button onClick={handleRedeemSubmit}>Process Redemption</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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