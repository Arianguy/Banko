import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Plus } from 'lucide-react';
import React, { useState } from 'react';

function formatINR(amount: number) {
    return '₹ ' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

const AddTransactionModal = ({ open, setOpen, sellData = null }: { open: boolean; setOpen: (v: boolean) => void; sellData?: any }) => {
    const [transactionType, setTransactionType] = useState('buy');
    const [stockName, setStockName] = useState('');
    const [selectedStock, setSelectedStock] = useState<any>(null);
    const [stockSuggestions, setStockSuggestions] = useState<any[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [exchange, setExchange] = useState('');
    const [date, setDate] = useState(new Date().toISOString().split('T')[0]); // Today's date
    const [quantity, setQuantity] = useState('');
    const [pricePerStock, setPricePerStock] = useState('');
    const [broker, setBroker] = useState('Zerodha'); // Default broker
    const [totalCharges, setTotalCharges] = useState('');
    const [netAmount, setNetAmount] = useState('');
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [searchLoading, setSearchLoading] = useState(false);
    const [maxQuantity, setMaxQuantity] = useState<number | null>(null);

    const { errors } = usePage().props as any;

    // Update form when sellData changes
    React.useEffect(() => {
        if (sellData) {
            setTransactionType('sell');
            setStockName(sellData.stock.symbol);
            setSelectedStock(sellData.stock);
            setExchange(sellData.stock.exchange);
            setPricePerStock(sellData.current_price?.toString() || '');
            setMaxQuantity(sellData.current_quantity);
            // Reset other fields for sell
            setQuantity('');
            setTotalCharges('');
            setNetAmount('');
            setNotes('');
        } else {
            // Reset to default state for new transaction
            setTransactionType('buy');
            setStockName('');
            setSelectedStock(null);
            setExchange('');
            setPricePerStock('');
            setMaxQuantity(null);
            setQuantity('');
            setTotalCharges('');
            setNetAmount('');
            setNotes('');
        }
    }, [sellData]);

    // Search stocks as user types
    const searchStocks = async (query: string) => {
        if (query.length < 2) {
            setStockSuggestions([]);
            setShowSuggestions(false);
            return;
        }

        setSearchLoading(true);
        try {
            const response = await fetch(`/equity-holding/search-stocks?q=${encodeURIComponent(query)}`);
            const stocks = await response.json();
            setStockSuggestions(stocks);
            setShowSuggestions(true);
        } catch (error) {
            console.error('Error searching stocks:', error);
        } finally {
            setSearchLoading(false);
        }
    };

    // Handle stock selection
    const selectStock = (stock: any) => {
        setSelectedStock(stock);
        setStockName(stock.symbol);
        setExchange(stock.exchange || 'NSE');
        setPricePerStock(stock.current_price ? stock.current_price.toString() : '');
        setShowSuggestions(false);

        // Auto-calculate net amount if we have price and quantity
        setTimeout(() => {
            if (quantity && stock.current_price && totalCharges) {
                const total = parseFloat(quantity) * parseFloat(stock.current_price);
                const net = total + parseFloat(totalCharges || '0');
                setNetAmount(net.toFixed(2));
            }
        }, 100);
    };

    // Calculate net amount when inputs change
    const calculateNetAmount = () => {
        if (transactionType === 'bonus') {
            setNetAmount('0');
            return;
        }

        if (quantity && pricePerStock) {
            const total = parseFloat(quantity) * parseFloat(pricePerStock);
            const charges = parseFloat(totalCharges || '0');

            if (transactionType === 'sell') {
                // For sell: net amount is what user receives (total - charges)
                const net = total - charges;
                setNetAmount(net.toFixed(2));
            } else {
                // For buy: net amount is what user pays (total + charges)
                const net = total + charges;
                setNetAmount(net.toFixed(2));
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        router.post(
            '/equity-holding',
            {
                transaction_type: transactionType,
                stock_name: stockName,
                exchange,
                date,
                quantity: parseInt(quantity),
                price_per_stock: parseFloat(pricePerStock),
                broker,
                total_charges: parseFloat(totalCharges || '0'),
                net_amount: parseFloat(netAmount),
                notes,
            },
            {
                onSuccess: () => {
                    setOpen(false);
                    // Reset form
                    setTransactionType('buy');
                    setStockName('');
                    setSelectedStock(null);
                    setStockSuggestions([]);
                    setShowSuggestions(false);
                    setExchange('');
                    setDate(new Date().toISOString().split('T')[0]);
                    setQuantity('');
                    setPricePerStock('');
                    setBroker('Zerodha');
                    setTotalCharges('');
                    setNetAmount('');
                    setNotes('');
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{transactionType === 'sell' ? 'Sell Stock' : 'Add Stock Transaction'}</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Transaction Type */}
                <div>
                    <Label>Transaction Type</Label>
                    <Select
                        value={transactionType}
                        onValueChange={(value) => {
                            setTransactionType(value);
                            if (value === 'bonus') {
                                setPricePerStock('0');
                                setTotalCharges('0');
                                setNetAmount('0');
                            }
                        }}
                        disabled={submitting}
                        required
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select Transaction Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="buy">Buy</SelectItem>
                            <SelectItem value="sell">Sell</SelectItem>
                            <SelectItem value="bonus">Bonus</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="relative">
                        <Label>Stock Name</Label>
                        <Input
                            value={stockName}
                            onChange={(e) => {
                                const value = e.target.value;
                                setStockName(value);
                                setSelectedStock(null);
                                searchStocks(value);
                            }}
                            onBlur={() => {
                                // Delay hiding suggestions to allow clicking
                                setTimeout(() => setShowSuggestions(false), 200);
                            }}
                            placeholder={
                                transactionType === 'sell' ? 'Stock selected for selling' : 'Search stocks from database (e.g., INFY, Infosys)'
                            }
                            disabled={submitting || transactionType === 'sell'}
                            required
                            className={searchLoading ? 'pr-10' : ''}
                        />
                        {searchLoading && (
                            <div className="absolute top-1/2 right-3 mt-3 -translate-y-1/2">
                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        )}

                        {/* Stock Suggestions Dropdown */}
                        {showSuggestions && stockSuggestions.length > 0 && (
                            <div className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                                {stockSuggestions.map((stock) => (
                                    <div
                                        key={stock.id}
                                        className="cursor-pointer border-b border-gray-100 px-4 py-2 last:border-b-0 hover:bg-gray-100"
                                        onClick={() => selectStock(stock)}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <div className="text-sm font-medium">{stock.symbol}</div>
                                                <div className="text-xs text-gray-500">{stock.name}</div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-sm font-medium">
                                                    {stock.current_price ? formatINR(stock.current_price) : 'N/A'}
                                                </div>
                                                <div className="text-xs text-gray-400">{stock.exchange}</div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {showSuggestions && stockSuggestions.length === 0 && stockName.length >= 2 && !searchLoading && (
                            <div className="absolute z-50 mt-1 w-full rounded-md border border-gray-200 bg-white p-3 shadow-lg">
                                <div className="text-sm text-gray-500">No stocks found. You can still enter manually.</div>
                            </div>
                        )}

                        {!selectedStock && stockName.length === 0 && (
                            <div className="mt-1 text-xs text-gray-500">💡 Searches from your local stock database. Prices are updated via API.</div>
                        )}
                        {errors?.stock_name && <div className="mt-1 text-xs text-red-500">{errors.stock_name}</div>}
                    </div>
                    <div>
                        <Label>Exchange</Label>
                        <Select value={exchange} onValueChange={setExchange} disabled={submitting} required>
                            <SelectTrigger>
                                <SelectValue placeholder="Select Exchange" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="NSE">NSE</SelectItem>
                                <SelectItem value="BSE">BSE</SelectItem>
                            </SelectContent>
                        </Select>
                        {/* {selectedStock && (
                            <div className="mt-1 text-xs text-blue-600">Suggested: {selectedStock.exchange} (you can change if needed)</div>
                        )} */}
                        {errors?.exchange && <div className="mt-1 text-xs text-red-500">{errors.exchange}</div>}
                    </div>
                    <div>
                        <Label>Date</Label>
                        <Input value={date} onChange={(e) => setDate(e.target.value)} type="date" disabled={submitting} required />
                        {errors?.date && <div className="mt-1 text-xs text-red-500">{errors.date}</div>}
                    </div>
                    <div>
                        <Label>
                            Quantity
                            {transactionType === 'sell' && maxQuantity && <span className="ml-2 text-sm text-gray-500">(Max: {maxQuantity})</span>}
                        </Label>
                        <Input
                            value={quantity}
                            onChange={(e) => {
                                setQuantity(e.target.value);
                                setTimeout(calculateNetAmount, 0);
                            }}
                            type="number"
                            placeholder={transactionType === 'sell' ? `Max ${maxQuantity || 0} shares` : '100'}
                            max={transactionType === 'sell' ? maxQuantity || undefined : undefined}
                            disabled={submitting || (transactionType === 'sell' && !selectedStock)}
                            required
                        />
                        {transactionType === 'sell' && maxQuantity && (
                            <div className="mt-1 text-xs text-blue-500">💡 You currently own {maxQuantity} shares</div>
                        )}
                        {errors?.quantity && <div className="mt-1 text-xs text-red-500">{errors.quantity}</div>}
                    </div>
                    <div>
                        <Label>Price/Stock</Label>
                        <Input
                            value={pricePerStock}
                            onChange={(e) => {
                                setPricePerStock(e.target.value);
                                setTimeout(calculateNetAmount, 0);
                            }}
                            type="number"
                            step="0.01"
                            placeholder={transactionType === 'bonus' ? '0 (Free bonus shares)' : '1631.10'}
                            disabled={submitting || transactionType === 'bonus'}
                            required
                            readOnly={transactionType === 'bonus'}
                        />
                        {transactionType === 'bonus' && <div className="mt-1 text-xs text-blue-500">💡 Bonus shares are free (₹0)</div>}
                        {errors?.price_per_stock && <div className="mt-1 text-xs text-red-500">{errors.price_per_stock}</div>}
                    </div>
                    <div>
                        <Label>
                            Broker <span className="text-gray-400">(optional)</span>
                        </Label>
                        <Select value={broker} onValueChange={setBroker} disabled={submitting}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select Broker" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Zerodha">Zerodha</SelectItem>
                                <SelectItem value="ICICI Direct">ICICI Direct</SelectItem>
                                <SelectItem value="HDFC Securities">HDFC Securities</SelectItem>
                                <SelectItem value="Angel Broking">Angel Broking</SelectItem>
                                <SelectItem value="Upstox">Upstox</SelectItem>
                                <SelectItem value="Kotak Securities">Kotak Securities</SelectItem>
                                <SelectItem value="Groww">Groww</SelectItem>
                                <SelectItem value="5paisa">5paisa</SelectItem>
                                <SelectItem value="Other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Total Charges</Label>
                        <Input
                            value={totalCharges}
                            onChange={(e) => {
                                setTotalCharges(e.target.value);
                                setTimeout(calculateNetAmount, 0);
                            }}
                            type="number"
                            step="0.01"
                            placeholder={
                                transactionType === 'bonus' ? '0 (No charges)' : transactionType === 'sell' ? '25.50 (STT + brokerage)' : '168.74'
                            }
                            disabled={submitting || transactionType === 'bonus'}
                            required
                            readOnly={transactionType === 'bonus'}
                        />
                        {transactionType === 'bonus' && <div className="mt-1 text-xs text-blue-500">💡 No charges for bonus shares</div>}
                        {transactionType === 'sell' && (
                            <div className="mt-1 text-xs text-orange-500">💡 Includes STT, brokerage, and transaction charges</div>
                        )}
                        {errors?.total_charges && <div className="mt-1 text-xs text-red-500">{errors.total_charges}</div>}
                    </div>
                    <div>
                        <Label>
                            Net Amount
                            <span className="text-sm text-gray-500">{transactionType === 'sell' ? ' (Amount received)' : ' (Amount paid)'}</span>
                        </Label>
                        <Input
                            value={netAmount}
                            onChange={(e) => setNetAmount(e.target.value)}
                            type="number"
                            step="0.01"
                            placeholder={transactionType === 'bonus' ? '0 (Free)' : transactionType === 'sell' ? '162500 (after charges)' : '163278'}
                            disabled={submitting || transactionType === 'bonus'}
                            required
                            readOnly={transactionType === 'bonus'}
                        />
                        {transactionType === 'bonus' && <div className="mt-1 text-xs text-blue-500">💡 Total amount is ₹0 for bonus shares</div>}
                        {transactionType === 'sell' && (
                            <div className="mt-1 text-xs text-green-500">💡 Amount you receive in your account after all charges</div>
                        )}
                        {errors?.net_amount && <div className="mt-1 text-xs text-red-500">{errors.net_amount}</div>}
                    </div>
                </div>
                <div>
                    <Label>
                        Notes <span className="text-gray-400">(optional)</span>
                    </Label>
                    <Input
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Note can be used for future reference"
                        disabled={submitting}
                    />
                </div>
                <DialogFooter className="pt-4">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" disabled={submitting}>
                        {submitting
                            ? transactionType === 'sell'
                                ? 'Selling...'
                                : 'Adding...'
                            : transactionType === 'sell'
                              ? 'Sell Shares'
                              : 'Add Transaction'}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
};

const EditTransactionModal = ({ open, setOpen, transaction }: { open: boolean; setOpen: (v: boolean) => void; transaction: any }) => {
    const [transactionType, setTransactionType] = useState(transaction?.transaction_type || 'buy');
    const [date, setDate] = useState(transaction?.transaction_date || '');
    const [quantity, setQuantity] = useState(transaction?.quantity?.toString() || '');
    const [pricePerStock, setPricePerStock] = useState(transaction?.price_per_stock?.toString() || '');
    const [broker, setBroker] = useState(transaction?.broker || 'Zerodha');
    const [totalCharges, setTotalCharges] = useState(transaction?.total_charges?.toString() || '');
    const [netAmount, setNetAmount] = useState(transaction?.net_amount?.toString() || '');
    const [notes, setNotes] = useState(transaction?.notes || '');
    const [submitting, setSubmitting] = useState(false);

    const { errors } = usePage().props as any;

    const calculateNetAmount = () => {
        if (transactionType === 'bonus') {
            setNetAmount('0');
            return;
        }

        if (quantity && pricePerStock) {
            const total = parseFloat(quantity) * parseFloat(pricePerStock);
            const charges = parseFloat(totalCharges || '0');

            if (transactionType === 'sell') {
                // For sell: net amount is what user receives (total - charges)
                const net = total - charges;
                setNetAmount(net.toFixed(2));
            } else {
                // For buy: net amount is what user pays (total + charges)
                const net = total + charges;
                setNetAmount(net.toFixed(2));
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        router.put(
            `/equity-holding/transactions/${transaction.id}`,
            {
                transaction_type: transactionType,
                date,
                quantity: parseInt(quantity),
                price_per_stock: parseFloat(pricePerStock),
                broker,
                total_charges: parseFloat(totalCharges || '0'),
                net_amount: parseFloat(netAmount),
                notes,
            },
            {
                onSuccess: () => {
                    setOpen(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    // Update form when transaction changes
    React.useEffect(() => {
        if (transaction) {
            setTransactionType(transaction.transaction_type || 'buy');
            // Format date to YYYY-MM-DD for input[type="date"]
            const formattedDate = transaction.transaction_date ? new Date(transaction.transaction_date).toISOString().split('T')[0] : '';
            setDate(formattedDate);
            setQuantity(transaction.quantity?.toString() || '');
            setPricePerStock(transaction.price_per_stock?.toString() || '');
            setBroker(transaction.broker || 'Zerodha');
            setTotalCharges(transaction.total_charges?.toString() || '');
            setNetAmount(transaction.net_amount?.toString() || '');
            setNotes(transaction.notes || '');
        }
    }, [transaction]);

    if (!transaction) return null;

    return (
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit Transaction</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Transaction Type */}
                <div>
                    <Label>Transaction Type</Label>
                    <Select
                        value={transactionType}
                        onValueChange={(value) => {
                            setTransactionType(value);
                            if (value === 'bonus') {
                                setPricePerStock('0');
                                setTotalCharges('0');
                                setNetAmount('0');
                            }
                        }}
                        disabled={submitting}
                        required
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select Transaction Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="buy">Buy</SelectItem>
                            <SelectItem value="sell">Sell</SelectItem>
                            <SelectItem value="bonus">Bonus</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label>Date</Label>
                        <Input value={date} onChange={(e) => setDate(e.target.value)} type="date" disabled={submitting} required />
                        {errors?.date && <div className="mt-1 text-xs text-red-500">{errors.date}</div>}
                    </div>
                    <div>
                        <Label>Quantity</Label>
                        <Input
                            value={quantity}
                            onChange={(e) => {
                                setQuantity(e.target.value);
                                setTimeout(calculateNetAmount, 0);
                            }}
                            type="number"
                            placeholder="100"
                            disabled={submitting}
                            required
                        />
                        {errors?.quantity && <div className="mt-1 text-xs text-red-500">{errors.quantity}</div>}
                    </div>
                    <div>
                        <Label>Price/Stock</Label>
                        <Input
                            value={pricePerStock}
                            onChange={(e) => {
                                setPricePerStock(e.target.value);
                                setTimeout(calculateNetAmount, 0);
                            }}
                            type="number"
                            step="0.01"
                            placeholder={transactionType === 'bonus' ? '0 (Free bonus shares)' : '1631.10'}
                            disabled={submitting || transactionType === 'bonus'}
                            required
                            readOnly={transactionType === 'bonus'}
                        />
                        {transactionType === 'bonus' && <div className="mt-1 text-xs text-blue-500">💡 Bonus shares are free (₹0)</div>}
                        {errors?.price_per_stock && <div className="mt-1 text-xs text-red-500">{errors.price_per_stock}</div>}
                    </div>
                    <div>
                        <Label>
                            Broker <span className="text-gray-400">(optional)</span>
                        </Label>
                        <Select value={broker} onValueChange={setBroker} disabled={submitting}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select Broker" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Zerodha">Zerodha</SelectItem>
                                <SelectItem value="ICICI Direct">ICICI Direct</SelectItem>
                                <SelectItem value="HDFC Securities">HDFC Securities</SelectItem>
                                <SelectItem value="Angel Broking">Angel Broking</SelectItem>
                                <SelectItem value="Upstox">Upstox</SelectItem>
                                <SelectItem value="Kotak Securities">Kotak Securities</SelectItem>
                                <SelectItem value="Groww">Groww</SelectItem>
                                <SelectItem value="5paisa">5paisa</SelectItem>
                                <SelectItem value="Other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Total Charges</Label>
                        <Input
                            value={totalCharges}
                            onChange={(e) => {
                                setTotalCharges(e.target.value);
                                setTimeout(calculateNetAmount, 0);
                            }}
                            type="number"
                            step="0.01"
                            placeholder={transactionType === 'bonus' ? '0 (No charges)' : '168.74'}
                            disabled={submitting || transactionType === 'bonus'}
                            required
                            readOnly={transactionType === 'bonus'}
                        />
                        {transactionType === 'bonus' && <div className="mt-1 text-xs text-blue-500">💡 No charges for bonus shares</div>}
                        {errors?.total_charges && <div className="mt-1 text-xs text-red-500">{errors.total_charges}</div>}
                    </div>
                    <div>
                        <Label>Net Amount</Label>
                        <Input
                            value={netAmount}
                            onChange={(e) => setNetAmount(e.target.value)}
                            type="number"
                            step="0.01"
                            placeholder={transactionType === 'bonus' ? '0 (Free)' : '163278'}
                            disabled={submitting || transactionType === 'bonus'}
                            required
                            readOnly={transactionType === 'bonus'}
                        />
                        {transactionType === 'bonus' && <div className="mt-1 text-xs text-blue-500">💡 Total amount is ₹0 for bonus shares</div>}
                        {errors?.net_amount && <div className="mt-1 text-xs text-red-500">{errors.net_amount}</div>}
                    </div>
                </div>
                <div>
                    <Label>
                        Notes <span className="text-gray-400">(optional)</span>
                    </Label>
                    <Input
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Note can be used for future reference"
                        disabled={submitting}
                    />
                </div>
                <DialogFooter className="pt-4">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" disabled={submitting}>
                        {submitting ? 'Updating...' : 'Update Transaction'}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
};

const HoldingsTable = ({
    holdings,
    openEditModal,
    openSellModal,
}: {
    holdings: any[];
    openEditModal: (transaction: any) => void;
    openSellModal: (holding: any) => void;
}) => {
    const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

    const toggleRow = (stockId: number) => {
        const newExpanded = new Set(expandedRows);
        if (newExpanded.has(stockId)) {
            newExpanded.delete(stockId);
        } else {
            newExpanded.add(stockId);
        }
        setExpandedRows(newExpanded);
    };

    return (
        <div className="mb-6 hidden w-full overflow-x-auto md:block">
            <table className="min-w-full divide-y divide-gray-200 rounded-lg bg-white text-xs shadow md:text-sm">
                <thead className="bg-gray-100">
                    <tr>
                        <th className="px-2 py-2 text-left font-semibold"></th>
                        <th className="px-2 py-2 text-left font-semibold">Stock</th>
                        <th className="px-2 py-2 text-left font-semibold">Quantity</th>
                        <th className="px-2 py-2 text-left font-semibold">Avg Price</th>
                        <th className="px-2 py-2 text-left font-semibold">Current Price</th>
                        <th className="px-2 py-2 text-left font-semibold">Investment</th>
                        <th className="px-2 py-2 text-left font-semibold">Current Value</th>
                        <th className="px-2 py-2 text-left font-semibold">P&L</th>
                        <th className="px-2 py-2 text-left font-semibold">P&L %</th>
                        <th className="px-2 py-2 text-left font-semibold">Weight %</th>
                        <th className="px-2 py-2 text-left font-semibold">52W Range</th>
                        <th className="px-2 py-2 text-left font-semibold">Sector</th>
                        <th className="px-2 py-2 text-left font-semibold">Dividend</th>
                        <th className="px-2 py-2 text-left font-semibold">Day Change</th>
                        <th className="px-2 py-2 text-left font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {holdings.map((holding, idx) => (
                        <>
                            <tr key={holding.stock_id} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                                <td className="cursor-pointer px-2 py-2 whitespace-nowrap" onClick={() => toggleRow(holding.stock_id)}>
                                    {holding.transaction_count > 1 &&
                                        (expandedRows.has(holding.stock_id) ? (
                                            <ChevronDown className="h-4 w-4" />
                                        ) : (
                                            <ChevronRight className="h-4 w-4" />
                                        ))}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    <div>
                                        <div className="font-medium">{holding.symbol}</div>
                                        <div className="text-sm text-gray-500">{holding.name}</div>
                                        <div className="text-xs text-gray-400">{holding.exchange}</div>
                                    </div>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">{holding.quantity}</td>
                                <td className="px-2 py-2 whitespace-nowrap">{formatINR(holding.avg_price)}</td>
                                <td className="px-2 py-2 whitespace-nowrap">{formatINR(holding.current_price)}</td>
                                <td className="px-2 py-2 whitespace-nowrap">{formatINR(holding.total_investment)}</td>
                                <td className="px-2 py-2 whitespace-nowrap">₹{Math.round(holding.current_value).toLocaleString('en-IN')}</td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    <span className={Math.round(holding.unrealized_gain_loss) >= 0 ? 'text-green-600' : 'text-red-600'}>
                                        ₹{Math.round(holding.unrealized_gain_loss).toLocaleString('en-IN')}
                                    </span>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    <span className={Math.round(holding.unrealized_gain_loss) >= 0 ? 'text-green-600' : 'text-red-600'}>
                                        {holding.unrealized_gain_loss_percent != null && !isNaN(holding.unrealized_gain_loss_percent)
                                            ? Number(holding.unrealized_gain_loss_percent).toFixed(2)
                                            : '0.00'}
                                        %
                                    </span>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    <span className="font-medium">{holding.weight_percent || 0}%</span>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {holding.week_52_high && holding.week_52_low ? (
                                        <div className="text-xs">
                                            <div className="text-green-600">H: {formatINR(holding.week_52_high)}</div>
                                            <div className="text-red-600">L: {formatINR(holding.week_52_low)}</div>
                                        </div>
                                    ) : (
                                        <span className="text-gray-400">-</span>
                                    )}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    <span className="rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">{holding.sector || 'Unknown'}</span>
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {holding.dividend_data && (
                                        <div className="text-xs">
                                            {holding.dividend_data.total_dividends_received > 0 && (
                                                <div className="font-medium text-green-600">
                                                    ₹{holding.dividend_data.total_dividends_received.toLocaleString('en-IN')}
                                                </div>
                                            )}
                                            {holding.dividend_data.pending_dividends > 0 && (
                                                <div className="text-orange-600">
                                                    Pending: ₹{holding.dividend_data.pending_dividends.toLocaleString('en-IN')}
                                                </div>
                                            )}
                                            {holding.dividend_data.dividend_yield > 0 && (
                                                <div className="text-blue-600">Yield: {holding.dividend_data.dividend_yield.toFixed(2)}%</div>
                                            )}
                                            {holding.dividend_data.has_upcoming_dividend && (
                                                <div className="font-medium text-purple-600">📅 Upcoming</div>
                                            )}
                                            {!holding.dividend_data.total_dividends_received &&
                                                !holding.dividend_data.pending_dividends &&
                                                !holding.dividend_data.has_upcoming_dividend && <span className="text-gray-400">No dividends</span>}
                                        </div>
                                    )}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {holding.day_change !== null && holding.day_change !== undefined ? (
                                        <span className={holding.day_change >= 0 ? 'text-green-600' : 'text-red-600'}>
                                            {formatINR(holding.day_change)}
                                            {holding.day_change_percent != null &&
                                                !isNaN(holding.day_change_percent) &&
                                                ` (${Number(holding.day_change_percent).toFixed(2)}%)`}
                                        </span>
                                    ) : (
                                        <span className="text-gray-400">-</span>
                                    )}
                                </td>
                                <td className="px-2 py-2 whitespace-nowrap">
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => openSellModal(holding)}
                                        className="h-7 px-2 text-xs"
                                        disabled={holding.quantity <= 0}
                                    >
                                        Sell
                                    </Button>
                                </td>
                            </tr>
                            {expandedRows.has(holding.stock_id) && (
                                <>
                                    {/* Transaction Header Row */}
                                    <tr className="border-t-2 border-gray-300 bg-gray-100">
                                        <td className="px-2 py-1 whitespace-nowrap"></td>
                                        <td className="px-2 py-1 pl-8 whitespace-nowrap">
                                            <div className="text-xs font-semibold text-gray-700">Transaction Details</div>
                                        </td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Qty</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Buy Price</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Date</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Investment</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Current Value</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">P&L</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Days Held</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Broker/Exchange</td>
                                        <td className="px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-700">Actions</td>
                                    </tr>
                                    {holding.transactions.map((transaction: any, index: number) => {
                                        const transactionCurrentValue = transaction.quantity * holding.current_price;
                                        const transactionPL = transactionCurrentValue - transaction.net_amount;
                                        const transactionPLPercent = (transactionPL / transaction.net_amount) * 100;

                                        return (
                                            <tr
                                                key={`${holding.stock_id}-${index}`}
                                                className={
                                                    transaction.is_sell ? 'border-b border-red-100 bg-red-50' : 'border-b border-blue-100 bg-blue-50'
                                                }
                                            >
                                                <td className="px-2 py-2 whitespace-nowrap"></td>
                                                <td className="px-2 py-2 pl-8 whitespace-nowrap">
                                                    <div className="text-sm font-medium">
                                                        #{index + 1}
                                                        {transaction.is_sell && (
                                                            <span className="ml-2 rounded bg-red-100 px-1 py-0.5 text-xs text-red-700">SELL</span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {new Date(transaction.transaction_date).toLocaleDateString('en-GB')}
                                                    </div>
                                                </td>
                                                <td className="px-2 py-2 font-medium whitespace-nowrap">
                                                    {transaction.is_sell ? `-${transaction.quantity}` : transaction.quantity}
                                                </td>
                                                <td className="px-2 py-2 whitespace-nowrap">{formatINR(transaction.price_per_stock)}</td>
                                                <td className="px-2 py-2 text-xs whitespace-nowrap">
                                                    {new Date(transaction.transaction_date).toLocaleDateString('en-GB')}
                                                </td>
                                                <td className="px-2 py-2 whitespace-nowrap">{formatINR(transaction.net_amount)}</td>
                                                <td className="px-2 py-2 font-medium whitespace-nowrap">{formatINR(transactionCurrentValue)}</td>
                                                <td className="px-2 py-2 whitespace-nowrap">
                                                    <div className={transactionPL >= 0 ? 'font-medium text-green-600' : 'font-medium text-red-600'}>
                                                        ₹{Math.round(transactionPL).toLocaleString('en-IN')}
                                                    </div>
                                                    <div className={`text-xs ${transactionPL >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                                                        ({transactionPLPercent.toFixed(2)}%)
                                                    </div>
                                                </td>
                                                <td className="px-2 py-2 whitespace-nowrap">
                                                    <div className="text-sm font-medium">{Math.abs(Math.round(transaction.days_held))} days</div>
                                                    <div
                                                        className={`rounded-full px-2 py-1 text-xs font-medium ${transaction.days_held > 365 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}
                                                    >
                                                        {transaction.days_held > 365 ? 'LTCG' : 'STCG'}
                                                    </div>
                                                </td>
                                                <td className="px-2 py-2 whitespace-nowrap">
                                                    {transaction.broker && (
                                                        <div className="text-xs font-medium text-gray-600">{transaction.broker}</div>
                                                    )}
                                                    <div className="text-xs text-gray-400">{transaction.exchange}</div>
                                                </td>
                                                <td className="px-2 py-2 whitespace-nowrap">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => openEditModal(transaction)}
                                                        className="h-7 w-7 p-1"
                                                    >
                                                        <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth={2}
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                                            />
                                                        </svg>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </>
                            )}
                        </>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

const HoldingsCards = ({ holdings, openSellModal }: { holdings: any[]; openSellModal: (holding: any) => void }) => {
    const [expandedCards, setExpandedCards] = useState<Set<number>>(new Set());

    const toggleCard = (stockId: number) => {
        const newExpanded = new Set(expandedCards);
        if (newExpanded.has(stockId)) {
            newExpanded.delete(stockId);
        } else {
            newExpanded.add(stockId);
        }
        setExpandedCards(newExpanded);
    };

    return (
        <div className="mb-6 flex flex-col gap-4 md:hidden">
            {holdings.map((holding) => (
                <Card key={holding.stock_id} className="w-full border border-gray-200 shadow">
                    <CardHeader className="pb-2">
                        <div className="flex flex-col md:flex-row md:items-center md:gap-4">
                            <CardTitle className="flex min-w-[180px] items-center gap-2">
                                {holding.symbol}
                                <span className="text-xs font-normal text-gray-400">({holding.exchange})</span>
                                {holding.transaction_count > 1 && (
                                    <Button size="sm" variant="ghost" onClick={() => toggleCard(holding.stock_id)} className="h-6 w-6 p-1">
                                        {expandedCards.has(holding.stock_id) ? (
                                            <ChevronDown className="h-3 w-3" />
                                        ) : (
                                            <ChevronRight className="h-3 w-3" />
                                        )}
                                    </Button>
                                )}
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex w-full flex-col text-sm md:flex-row md:items-center md:gap-6">
                            <div className="flex-1 md:w-1/4">
                                <span className="font-medium">Investment:</span> {formatINR(holding.total_investment)}
                            </div>
                            <div className="flex-1 md:w-1/4">
                                <span className="font-medium">Current Value:</span> {formatINR(holding.current_value)}
                            </div>
                            <div className="flex-1 md:w-1/4">
                                <span className="font-medium">P&L:</span>
                                <span className={holding.unrealized_gain_loss >= 0 ? 'ml-1 text-green-600' : 'ml-1 text-red-600'}>
                                    {formatINR(holding.unrealized_gain_loss)} (
                                    {holding.unrealized_gain_loss_percent != null && !isNaN(holding.unrealized_gain_loss_percent)
                                        ? Number(holding.unrealized_gain_loss_percent).toFixed(2)
                                        : '0.00'}
                                    %)
                                </span>
                            </div>
                            <div className="flex-1 md:w-1/4">
                                <span className="font-medium">Current Price:</span> {formatINR(holding.current_price)}
                            </div>
                        </div>

                        {/* Dividend Information for Mobile */}
                        {holding.dividend_data &&
                            (holding.dividend_data.total_dividends_received > 0 ||
                                holding.dividend_data.pending_dividends > 0 ||
                                holding.dividend_data.has_upcoming_dividend) && (
                                <div className="mt-3 border-t pt-3">
                                    <div className="mb-2 text-sm font-medium text-gray-700">Dividend Information</div>
                                    <div className="flex flex-wrap gap-2 text-sm">
                                        {holding.dividend_data.total_dividends_received > 0 && (
                                            <span className="rounded bg-green-100 px-2 py-1 text-xs text-green-800">
                                                Received: ₹{holding.dividend_data.total_dividends_received.toLocaleString('en-IN')}
                                            </span>
                                        )}
                                        {holding.dividend_data.pending_dividends > 0 && (
                                            <span className="rounded bg-orange-100 px-2 py-1 text-xs text-orange-800">
                                                Pending: ₹{holding.dividend_data.pending_dividends.toLocaleString('en-IN')}
                                            </span>
                                        )}
                                        {holding.dividend_data.dividend_yield > 0 && (
                                            <span className="rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">
                                                Yield: {holding.dividend_data.dividend_yield.toFixed(2)}%
                                            </span>
                                        )}
                                        {holding.dividend_data.has_upcoming_dividend && (
                                            <span className="rounded bg-purple-100 px-2 py-1 text-xs text-purple-800">📅 Upcoming Dividend</span>
                                        )}
                                    </div>
                                </div>
                            )}

                        <div className="mt-2 flex-shrink-0 md:mt-0">
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => openSellModal(holding)}
                                className="h-7 px-3 text-xs"
                                disabled={holding.quantity <= 0}
                            >
                                Sell
                            </Button>
                        </div>

                        {expandedCards.has(holding.stock_id) && (
                            <div className="mt-4 space-y-2">
                                <div className="text-sm font-medium">Transactions:</div>
                                {holding.transactions.map((transaction: any, index: number) => (
                                    <div
                                        key={index}
                                        className={
                                            transaction.is_sell
                                                ? 'rounded border border-red-200 bg-red-50 p-2 text-xs'
                                                : 'rounded bg-gray-50 p-2 text-xs'
                                        }
                                    >
                                        <div className="flex justify-between">
                                            <span>
                                                #{index + 1} - {transaction.is_sell ? `-${transaction.quantity}` : transaction.quantity} shares
                                                {transaction.is_sell && (
                                                    <span className="ml-2 rounded bg-red-100 px-1 py-0.5 text-xs text-red-700">SELL</span>
                                                )}
                                            </span>
                                            <span>{new Date(transaction.transaction_date).toLocaleDateString('en-GB')}</span>
                                        </div>
                                        <div className="mt-1 flex justify-between">
                                            <span>Price: {formatINR(transaction.price_per_stock)}</span>
                                            <span className={transaction.is_sell ? 'text-green-600' : ''}>
                                                {transaction.is_sell ? 'Received' : 'Amount'}: {formatINR(transaction.net_amount)}
                                            </span>
                                        </div>
                                        {transaction.broker && <div className="text-gray-500">Broker: {transaction.broker}</div>}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
};

const SoldHistoryTable = ({ soldHistory }: { soldHistory: any[] }) => {
    if (soldHistory.length === 0) {
        return (
            <Card className="mb-6">
                <CardContent className="p-6 text-center">
                    <p className="text-gray-500">No sold transactions found.</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle className="text-lg font-semibold">Sold Transactions History</CardTitle>
                <p className="text-sm text-gray-600">Complete history of sold positions with ROI details</p>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-900">Stock</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-900">Sell Date</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Quantity</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Avg Buy Price</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Sell Price</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Investment</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Sale Proceeds</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Realized P&L</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">ROI %</th>
                                <th className="px-4 py-3 text-right font-medium text-gray-900">Days Held</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {soldHistory.map((transaction, index) => (
                                <tr key={index} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <div>
                                            <div className="font-medium text-gray-900">{transaction.symbol}</div>
                                            <div className="text-xs text-gray-500">{transaction.stock_name}</div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-gray-900">{new Date(transaction.sell_date).toLocaleDateString('en-IN')}</td>
                                    <td className="px-4 py-3 text-right text-gray-900">{transaction.quantity}</td>
                                    <td className="px-4 py-3 text-right text-gray-900">{formatINR(transaction.avg_buy_price)}</td>
                                    <td className="px-4 py-3 text-right text-gray-900">{formatINR(transaction.sell_price)}</td>
                                    <td className="px-4 py-3 text-right text-gray-900">{formatINR(transaction.total_investment)}</td>
                                    <td className="px-4 py-3 text-right text-gray-900">{formatINR(transaction.sale_proceeds)}</td>
                                    <td
                                        className={`px-4 py-3 text-right font-medium ${
                                            transaction.realized_pl >= 0 ? 'text-green-600' : 'text-red-600'
                                        }`}
                                    >
                                        {transaction.realized_pl >= 0 ? '+' : ''}
                                        {formatINR(transaction.realized_pl)}
                                    </td>
                                    <td
                                        className={`px-4 py-3 text-right font-medium ${
                                            transaction.roi_percent >= 0 ? 'text-green-600' : 'text-red-600'
                                        }`}
                                    >
                                        {transaction.roi_percent >= 0 ? '+' : ''}
                                        {transaction.roi_percent.toFixed(2)}%
                                    </td>
                                    <td className="px-4 py-3 text-right text-gray-900">{transaction.days_held}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Summary Row */}
                <div className="mt-4 border-t pt-4">
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div className="text-center">
                            <div className="text-sm text-gray-500">Total Transactions</div>
                            <div className="text-lg font-semibold">{soldHistory.length}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-sm text-gray-500">Total Investment</div>
                            <div className="text-lg font-semibold">{formatINR(soldHistory.reduce((sum, t) => sum + t.total_investment, 0))}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-sm text-gray-500">Total Proceeds</div>
                            <div className="text-lg font-semibold">{formatINR(soldHistory.reduce((sum, t) => sum + t.sale_proceeds, 0))}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-sm text-gray-500">Total Realized P&L</div>
                            <div
                                className={`text-lg font-semibold ${
                                    soldHistory.reduce((sum, t) => sum + t.realized_pl, 0) >= 0 ? 'text-green-600' : 'text-red-600'
                                }`}
                            >
                                {soldHistory.reduce((sum, t) => sum + t.realized_pl, 0) >= 0 ? '+' : ''}
                                {formatINR(soldHistory.reduce((sum, t) => sum + t.realized_pl, 0))}
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

const EquityHoldingPage = () => {
    const { holdings, portfolioMetrics, dividendSummary } = usePage().props as any;
    const [modalOpen, setModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editingTransaction, setEditingTransaction] = useState<any>(null);
    const [syncing, setSyncing] = useState(false);
    const [sellModalOpen, setSellModalOpen] = useState(false);
    const [sellData, setSellData] = useState<any>(null);
    const [showHistory, setShowHistory] = useState(false);
    const [soldHistory, setSoldHistory] = useState<any[]>([]);

    // Calculate summary
    const totalInvestment = holdings.reduce((sum: number, h: any) => sum + h.total_investment, 0);
    const totalCurrentValue = holdings.reduce((sum: number, h: any) => sum + h.current_value, 0);
    const totalGainLoss = totalCurrentValue - totalInvestment;
    const totalGainLossPercent = totalInvestment > 0 ? (totalGainLoss / totalInvestment) * 100 : 0;

    const openEditModal = (transaction: any) => {
        setEditingTransaction(transaction);
        setEditModalOpen(true);
    };

    const openSellModal = async (holding: any) => {
        try {
            const response = await fetch(`/equity-holding/${holding.stock_id}/holding-info`);
            if (response.ok) {
                const data = await response.json();
                setSellData(data);
                setSellModalOpen(true);
            } else {
                alert('❌ Failed to load holding information for selling.');
            }
        } catch (error) {
            console.error('Error fetching holding info:', error);
            alert('❌ Failed to load holding information for selling.');
        }
    };

    const fetchSoldHistory = async () => {
        try {
            const response = await fetch('/equity-holding/sold-history');
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

    const syncPrices = async () => {
        setSyncing(true);
        try {
            const response = await fetch('/equity-holding/sync-prices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });

            if (response.ok) {
                window.location.reload();
            } else {
                alert('❌ Failed to sync prices. Please try again.');
            }
        } catch (error) {
            alert('❌ Failed to sync prices. Please check your connection.');
        } finally {
            setSyncing(false);
        }
    };

    const syncAllData = async () => {
        setSyncing(true);
        try {
            // Get stock IDs from current holdings
            const userStockIds = holdings.map((holding: any) => holding.stock_id);

            // First sync prices for user's stocks only
            const priceResponse = await fetch('/equity-holding/sync-prices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    stock_ids: userStockIds,
                }),
            });

            if (!priceResponse.ok) {
                throw new Error('Failed to sync prices');
            }

            // Then sync dividends for user's stocks only
            const dividendResponse = await fetch('/equity-holding/update-dividend-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    stock_ids: userStockIds,
                }),
            });

            if (dividendResponse.ok) {
                const data = await dividendResponse.json();

                // Refresh only the equity holding data without full page reload
                await refreshEquityData();

                alert(`✅ Prices and dividends synced successfully! ${data.message}`);
            } else {
                // Still refresh data even if dividend sync failed
                await refreshEquityData();
                alert('✅ Prices synced, but dividend sync failed. Please try dividend sync separately.');
            }
        } catch (error) {
            alert('❌ Failed to sync data. Please check your connection.');
        } finally {
            setSyncing(false);
        }
    };

    const refreshEquityData = async () => {
        try {
            const response = await fetch('/equity-holding', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                // Use Inertia's visit to reload just this page's data
                router.reload({ only: ['holdings', 'portfolioMetrics', 'dividendSummary'] });
            }
        } catch (error) {
            console.error('Failed to refresh data:', error);
        }
    };

    React.useEffect(() => {
        if (showHistory) {
            fetchSoldHistory();
        }
    }, [showHistory]);

    return (
        <div className="p-4">
            {/* Action buttons at the top */}
            <div className="mb-4 flex items-center justify-between">
                <div className="flex gap-3">
                    <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                        <DialogTrigger asChild>
                            <Button variant="default">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Transaction
                            </Button>
                        </DialogTrigger>
                        <AddTransactionModal open={modalOpen} setOpen={setModalOpen} />
                    </Dialog>

                    <Button
                        variant="outline"
                        size="sm"
                        className="border-blue-200 text-blue-700 hover:bg-blue-50"
                        disabled={syncing}
                        onClick={syncAllData}
                    >
                        <svg className={`mr-2 h-4 w-4 ${syncing ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        {syncing ? 'Syncing...' : 'Sync All Data'}
                    </Button>
                </div>

                <div className="flex items-center gap-3">
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={showHistory}
                            onChange={(e) => setShowHistory(e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        Show Sold History
                    </label>
                </div>
            </div>

            {/* Summary Cards - Compact Design */}
            <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                <Card className="border-l-4 border-l-blue-500">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Total Investment</div>
                        <div className="text-lg font-bold">{formatINR(totalInvestment)}</div>
                    </CardContent>
                </Card>
                <Card className="border-l-4 border-l-purple-500">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Current Value</div>
                        <div className="text-lg font-bold">₹{Math.round(totalCurrentValue).toLocaleString('en-IN')}</div>
                    </CardContent>
                </Card>
                <Card className="border-l-4 border-l-orange-500">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Total P&L</div>
                        <div className={`text-lg font-bold ${totalGainLoss >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {totalGainLoss >= 0 ? '+' : ''}₹{Math.round(totalGainLoss).toLocaleString('en-IN')}
                        </div>
                        <div className={`text-xs ${totalGainLossPercent >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                            ({totalGainLossPercent >= 0 ? '+' : ''}
                            {totalGainLossPercent.toFixed(2)}%)
                        </div>
                    </CardContent>
                </Card>
                <Card className="border-l-4 border-l-green-500">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Dividends Received</div>
                        <div className="text-lg font-bold text-green-600">
                            ₹{Math.round(dividendSummary?.total_received_amount || 0).toLocaleString('en-IN')}
                        </div>
                        {dividendSummary?.pending_amount > 0 && (
                            <div className="text-xs text-orange-600">
                                Pending: ₹{Math.round(dividendSummary.pending_amount).toLocaleString('en-IN')}
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card className="border-l-4 border-l-red-500">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Today's Change</div>
                        <div className={`text-lg font-bold ${portfolioMetrics?.todaysChange >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {portfolioMetrics?.todaysChange >= 0 ? '+' : ''}₹{Math.round(portfolioMetrics?.todaysChange || 0).toLocaleString('en-IN')}
                        </div>
                        <div className={`text-xs ${portfolioMetrics?.todaysChangePercent >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                            ({portfolioMetrics?.todaysChangePercent >= 0 ? '+' : ''}
                            {portfolioMetrics?.todaysChangePercent?.toFixed(2) || '0.00'}%)
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Additional Summary Cards - Compact */}
            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card className="border-l-4 border-l-green-400">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Best Performer</div>
                        {portfolioMetrics?.bestPerformers?.length > 0 ? (
                            <>
                                <div className="text-sm font-bold text-green-600">{portfolioMetrics.bestPerformers[0].symbol}</div>
                                <div className="text-xs text-green-500">+{portfolioMetrics.bestPerformers[0].plPercent?.toFixed(2)}%</div>
                            </>
                        ) : (
                            <div className="text-sm font-bold text-gray-400">-</div>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-l-4 border-l-red-400">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Worst Performer</div>
                        {portfolioMetrics?.worstPerformers?.length > 0 ? (
                            <>
                                <div className="text-sm font-bold text-red-600">{portfolioMetrics.worstPerformers[0].symbol}</div>
                                <div className="text-xs text-red-500">{portfolioMetrics.worstPerformers[0].plPercent?.toFixed(2)}%</div>
                            </>
                        ) : (
                            <div className="text-sm font-bold text-gray-400">-</div>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-l-4 border-l-blue-400">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Portfolio Diversity</div>
                        <div className="text-sm font-bold">{portfolioMetrics?.portfolioDiversity?.totalHoldings || 0} Holdings</div>
                        <div className="text-xs text-gray-500">
                            Max: {portfolioMetrics?.portfolioDiversity?.maxConcentration?.toFixed(1) || '0.0'}%
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-l-4 border-l-purple-400">
                    <CardContent className="p-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Upcoming Dividends</div>
                        <div className="text-sm font-bold text-purple-600">{dividendSummary?.upcoming_dividends?.length || 0}</div>
                        <div className="text-xs text-gray-500">
                            {dividendSummary?.upcoming_dividends?.length > 0 ? 'Expected Soon' : 'None Scheduled'}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Sold History Table */}
            {showHistory && <SoldHistoryTable soldHistory={soldHistory} />}

            {/* Holdings Table */}
            {!showHistory && holdings.length > 0 ? (
                <>
                    <HoldingsTable holdings={holdings} openEditModal={openEditModal} openSellModal={openSellModal} />
                    <HoldingsCards holdings={holdings} openSellModal={openSellModal} />
                </>
            ) : !showHistory ? (
                <Card>
                    <CardContent className="p-6 text-center">
                        <p className="text-gray-500">No equity holdings found. Add your first transaction to get started.</p>
                    </CardContent>
                </Card>
            ) : null}

            {/* Edit Transaction Modal */}
            <Dialog open={editModalOpen} onOpenChange={setEditModalOpen}>
                <EditTransactionModal open={editModalOpen} setOpen={setEditModalOpen} transaction={editingTransaction} />
            </Dialog>

            {/* Sell Modal */}
            <Dialog open={sellModalOpen} onOpenChange={setSellModalOpen}>
                <AddTransactionModal open={sellModalOpen} setOpen={setSellModalOpen} sellData={sellData} />
            </Dialog>
        </div>
    );
};

// Assign the layout
EquityHoldingPage.layout = (page: any) => <AppSidebarLayout breadcrumbs={[{ title: 'Equity Holding', href: '/equity-holding' }]} children={page} />;

export default EquityHoldingPage;
