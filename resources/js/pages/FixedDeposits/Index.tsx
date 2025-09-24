import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { router, usePage } from '@inertiajs/react';
import { CheckCircle, Pencil, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

// BANKS are now dynamic from the backend, not hardcoded.

function getDaysBalance(maturity_date: string) {
    const today = new Date();
    const maturity = new Date(maturity_date);
    const diff = maturity.getTime() - today.setHours(0, 0, 0, 0);
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function renderDaysBalanceBadge(maturity_date: string) {
    const daysBalance = getDaysBalance(maturity_date);
    const isNegativeOrZero = daysBalance <= 0;
    
    return (
        <Badge 
            variant={isNegativeOrZero ? "destructive" : "default"}
            className={isNegativeOrZero ? "bg-red-500 text-white" : "bg-green-500 text-white"}
        >
            {daysBalance}
        </Badge>
    );
}

const BankBalanceModal = ({ open, setOpen, banks }: { open: boolean; setOpen: (v: boolean) => void; banks: Array<{ id: number, name: string }> }) => {
    const [updateDate, setUpdateDate] = useState('');
    const [bankBalances, setBankBalances] = useState([{
        bank_id: '',
        account_id: '',
        balance: ''
    }]);
    const [submitting, setSubmitting] = useState(false);
    const [clientErrors, setClientErrors] = useState<any>({});
    const [bankAccounts, setBankAccounts] = useState<{[key: string]: Array<{id: number, account_number: string, account_type: string}>}>({});
    const [showAddAccountModal, setShowAddAccountModal] = useState(false);
    const [newAccount, setNewAccount] = useState({ bank_id: '', account_number: '', account_type: '' });
    const [addingAccount, setAddingAccount] = useState(false);
    const { errors } = usePage().props as any;

    // Reset form when modal opens
    useEffect(() => {
        if (open) {
            setClientErrors({});
            setUpdateDate('');
            setBankBalances([{
                bank_id: '',
                account_id: '',
                balance: ''
            }]);
        }
    }, [open]);

    // Fetch accounts when bank is selected
    const fetchAccountsForBank = async (bankId: string) => {
        if (!bankId || bankAccounts[bankId]) return;
        
        try {
            const response = await fetch(`/banks/${bankId}/accounts`);
            const accounts = await response.json();
            setBankAccounts(prev => ({ ...prev, [bankId]: accounts }));
        } catch (error) {
            console.error('Error fetching accounts:', error);
        }
    };

    const addBankBalance = () => {
        setBankBalances([...bankBalances, {
            bank_id: '',
            account_id: '',
            balance: ''
        }]);
    };

    const removeBankBalance = (index: number) => {
        if (bankBalances.length > 1) {
            setBankBalances(bankBalances.filter((_, i) => i !== index));
        }
    };

    const updateBankBalance = (index: number, field: string, value: string) => {
        const updated = [...bankBalances];
        updated[index] = { ...updated[index], [field]: value };
        
        // If bank is changed, reset account and fetch accounts
        if (field === 'bank_id') {
            updated[index].account_id = '';
            if (value) {
                fetchAccountsForBank(value);
            }
        }
        
        setBankBalances(updated);
    };

    const handleSubmit = () => {
        setSubmitting(true);
        setClientErrors({});

        // Basic validation
        const errors: any = {};
        if (!updateDate) errors.update_date = 'Update date is required';
        
        bankBalances.forEach((balance, index) => {
            if (!balance.bank_id) errors[`bank_balances.${index}.bank_id`] = 'Bank is required';
            if (!balance.account_id) errors[`bank_balances.${index}.account_id`] = 'Account is required';
            if (!balance.balance) errors[`bank_balances.${index}.balance`] = 'Balance is required';
        });

        if (Object.keys(errors).length > 0) {
            setClientErrors(errors);
            setSubmitting(false);
            return;
        }

        // Convert account_id to account_number for backend
        const formattedBalances = bankBalances.map(balance => {
            const account = bankAccounts[balance.bank_id]?.find(acc => acc.id.toString() === balance.account_id);
            return {
                bank_id: balance.bank_id,
                account_number: account?.account_number || '',
                balance: balance.balance
            };
        });

        router.post('/bank-balances', {
            update_date: updateDate,
            bank_balances: formattedBalances
        }, {
            onSuccess: () => {
                setOpen(false);
            },
            onError: () => {
                setSubmitting(false);
            },
            onFinish: () => {
                setSubmitting(false);
            }
        });
    };

    const handleAddAccount = async () => {
        if (!newAccount.bank_id || !newAccount.account_number) {
            return;
        }

        setAddingAccount(true);
        try {
            const response = await fetch('/bank-accounts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(newAccount)
            });

            const result = await response.json();
            if (result.success) {
                // Update local accounts state
                setBankAccounts(prev => ({
                    ...prev,
                    [newAccount.bank_id]: [...(prev[newAccount.bank_id] || []), result.account]
                }));
                
                // Reset form and close modal
                setNewAccount({ bank_id: '', account_number: '', account_type: '' });
                setShowAddAccountModal(false);
            }
        } catch (error) {
            console.error('Error adding account:', error);
        } finally {
            setAddingAccount(false);
        }
    };

    return (
        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Update Bank Balance</DialogTitle>
            </DialogHeader>
            <div className="space-y-4">
                <div>
                    <Label htmlFor="update_date">Update Date</Label>
                    <Input
                        id="update_date"
                        type="date"
                        value={updateDate}
                        onChange={(e) => setUpdateDate(e.target.value)}
                        className={clientErrors.update_date || errors?.update_date ? 'border-red-500' : ''}
                    />
                    {(clientErrors.update_date || errors?.update_date) && (
                        <p className="text-red-500 text-sm mt-1">{clientErrors.update_date || errors?.update_date}</p>
                    )}
                </div>

                {bankBalances.map((balance, index) => (
                    <div key={index} className="border rounded-lg p-4 space-y-3">
                        <div className="flex justify-between items-center">
                            <h4 className="font-medium">Bank Balance {index + 1}</h4>
                            {bankBalances.length > 1 && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => removeBankBalance(index)}
                                >
                                    Remove
                                </Button>
                            )}
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <Label htmlFor={`bank_${index}`}>Bank</Label>
                                <Select
                                    value={balance.bank_id}
                                    onValueChange={(value) => updateBankBalance(index, 'bank_id', value)}
                                >
                                    <SelectTrigger className={clientErrors[`bank_balances.${index}.bank_id`] || errors?.[`bank_balances.${index}.bank_id`] ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="Select Bank" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {banks.map((bank) => (
                                            <SelectItem key={bank.id} value={bank.id.toString()}>
                                                {bank.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {(clientErrors[`bank_balances.${index}.bank_id`] || errors?.[`bank_balances.${index}.bank_id`]) && (
                                    <p className="text-red-500 text-sm mt-1">{clientErrors[`bank_balances.${index}.bank_id`] || errors?.[`bank_balances.${index}.bank_id`]}</p>
                                )}
                            </div>
                            
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <Label htmlFor={`account_${index}`}>Account Number</Label>
                                    {balance.bank_id && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setNewAccount({ ...newAccount, bank_id: balance.bank_id });
                                                setShowAddAccountModal(true);
                                            }}
                                            className="h-6 w-6 p-0 bg-black text-white hover:bg-gray-800 border-black"
                                        >
                                            +
                                        </Button>
                                    )}
                                </div>
                                <Select
                                    value={balance.account_id}
                                    onValueChange={(value) => updateBankBalance(index, 'account_id', value)}
                                    disabled={!balance.bank_id}
                                >
                                    <SelectTrigger className={clientErrors[`bank_balances.${index}.account_id`] || errors?.[`bank_balances.${index}.account_id`] ? 'border-red-500' : ''}>
                                        <SelectValue placeholder={balance.bank_id ? "Select Account" : "Select Bank First"} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {bankAccounts[balance.bank_id]?.map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                {account.account_number} {account.account_type && `(${account.account_type})`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {(clientErrors[`bank_balances.${index}.account_id`] || errors?.[`bank_balances.${index}.account_id`]) && (
                                    <p className="text-red-500 text-sm mt-1">{clientErrors[`bank_balances.${index}.account_id`] || errors?.[`bank_balances.${index}.account_id`]}</p>
                                )}
                            </div>
                            
                            <div>
                                <Label htmlFor={`balance_${index}`}>Balance</Label>
                                <Input
                                    id={`balance_${index}`}
                                    type="number"
                                    step="0.01"
                                    value={balance.balance}
                                    onChange={(e) => updateBankBalance(index, 'balance', e.target.value)}
                                    placeholder="Balance Amount"
                                    className={clientErrors[`bank_balances.${index}.balance`] || errors?.[`bank_balances.${index}.balance`] ? 'border-red-500' : ''}
                                />
                                {(clientErrors[`bank_balances.${index}.balance`] || errors?.[`bank_balances.${index}.balance`]) && (
                                    <p className="text-red-500 text-sm mt-1">{clientErrors[`bank_balances.${index}.balance`] || errors?.[`bank_balances.${index}.balance`]}</p>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
                
                <Button
                    type="button"
                    variant="outline"
                    onClick={addBankBalance}
                    className="w-full"
                >
                    + Add Another Bank Balance
                </Button>
            </div>
            
            <DialogFooter>
                <DialogClose asChild>
                    <Button variant="outline" disabled={submitting}>
                        Cancel
                    </Button>
                </DialogClose>
                <Button onClick={handleSubmit} disabled={submitting}>
                    {submitting ? 'Saving...' : 'Save Bank Balances'}
                </Button>
            </DialogFooter>
            
            {/* Add Account Modal */}
            <Dialog open={showAddAccountModal} onOpenChange={setShowAddAccountModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add New Account</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="new_bank">Bank</Label>
                            <Select
                                value={newAccount.bank_id}
                                onValueChange={(value) => setNewAccount({ ...newAccount, bank_id: value })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Bank" />
                                </SelectTrigger>
                                <SelectContent>
                                    {banks.map((bank) => (
                                        <SelectItem key={bank.id} value={bank.id.toString()}>
                                            {bank.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        
                        <div>
                            <Label htmlFor="new_account_number">Account Number</Label>
                            <Input
                                id="new_account_number"
                                value={newAccount.account_number}
                                onChange={(e) => setNewAccount({ ...newAccount, account_number: e.target.value })}
                                placeholder="Enter account number"
                            />
                        </div>
                        
                        <div>
                            <Label htmlFor="new_account_type">Account Type (Optional)</Label>
                            <Input
                                id="new_account_type"
                                value={newAccount.account_type}
                                onChange={(e) => setNewAccount({ ...newAccount, account_type: e.target.value })}
                                placeholder="e.g., Savings, Current"
                            />
                        </div>
                    </div>
                    
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={addingAccount}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button 
                            onClick={handleAddAccount} 
                            disabled={addingAccount || !newAccount.bank_id || !newAccount.account_number}
                        >
                            {addingAccount ? 'Adding...' : 'Add Account'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </DialogContent>
    );
};

const AddDepositModal = ({ open, setOpen, fd, isEdit, banks, onAddBank }: { open: boolean; setOpen: (v: boolean) => void; fd?: any; isEdit?: boolean, banks: Array<{ id: number, name: string }>, onAddBank: (name: string) => void }) => {
    // Helper to format date string (YYYY-MM-DDTHH:mm:ss...) to YYYY-MM-DD for input[type=date]
    // Format JS Date or string to DD/MM/YYYY
    const formatDateDDMMYYYY = (dateString: string | undefined | null): string => {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    };

    // Parse DD/MM/YYYY to YYYY-MM-DD
    const parseDateFromDDMMYYYY = (ddmmyyyy: string): string => {
        const [day, month, year] = ddmmyyyy.split('/');
        if (!day || !month || !year) return '';
        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    };

    const [bank, setBank] = useState(fd?.bank || (banks[0]?.name || ''));
    const [accountno, setAccountno] = useState(fd?.accountno || '');
    const [principal_amt, setPrincipalAmt] = useState(fd?.principal_amt || '');
    const [maturity_amt, setMaturityAmt] = useState(fd?.maturity_amt || '');
    // Use YYYY-MM-DD for input[type=date]
    const [start_date, setStartDate] = useState(fd?.start_date ? fd.start_date.slice(0, 10) : '');
    const [maturity_date, setMaturityDate] = useState(fd?.maturity_date ? fd.maturity_date.slice(0, 10) : '');
    const [int_rate, setIntRate] = useState(fd?.int_rate || '');
    const [submitting, setSubmitting] = useState(false);
    const [clientErrors, setClientErrors] = useState<any>({});
    const { errors } = usePage().props as any;

    // Add Bank Modal state
    const [addBankModal, setAddBankModal] = useState(false);
    const [newBankName, setNewBankName] = useState('');
    const [addBankError, setAddBankError] = useState('');

    // Calculate term (days) from start_date and maturity_date
    let term = '';
    if (start_date && maturity_date) {
        const d1 = new Date(start_date);
        const d2 = new Date(maturity_date);
        if (!isNaN(d1.getTime()) && !isNaN(d2.getTime())) {
            term = Math.ceil((d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24)).toString();
        }
    }

    // Calculated fields
    const principal = parseFloat(principal_amt) || 0;
    const maturity = parseFloat(maturity_amt) || 0;
    const termVal = parseFloat(term) || 0;
    const Int_amt = maturity - principal;
    const Int_year = termVal ? ((Int_amt / termVal) * 365).toFixed(2) : '';

    // Reset form and errors when modal opens
    useEffect(() => {
        if (open) {
            setClientErrors({});
        }
    }, [open]);

    // Close modal after successful save
    useEffect(() => {
        if (!submitting) return;
        if (Object.keys(errors || {}).length === 0 && submitting) {
            setOpen(false);
            setBank('SBI');
            setAccountno('');
            setPrincipalAmt('');
            setMaturityAmt('');
            setStartDate('');
            setMaturityDate('');
            setIntRate('');
            setSubmitting(false);
        }
    }, [errors, submitting, setOpen]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Client-side validation
        const newErrors: any = {};
        if (!bank) newErrors.bank = 'Bank is required';
        if (!accountno) newErrors.accountno = 'Account No is required';
        if (!principal_amt) newErrors.principal_amt = 'Principal Amount is required';
        if (!maturity_amt) newErrors.maturity_amt = 'Maturity Amount is required';
        if (!start_date) newErrors.start_date = 'Start Date is required';
        if (!maturity_date) newErrors.maturity_date = 'Maturity Date is required';
        if (!term) newErrors.term = 'Term is required';
        if (!int_rate) newErrors.int_rate = 'Interest Rate is required';
        setClientErrors(newErrors);
        if (Object.keys(newErrors).length > 0) return;
        setSubmitting(true);
        if (isEdit && fd?.id) {
            router.put(
                `/fixed-deposits/${fd.id}`,
                {
                    bank,
                    accountno,
                    principal_amt,
                    maturity_amt,
                    start_date,
                    maturity_date,
                    term: termVal,
                    int_rate,
                    Int_amt,
                    Int_year,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setOpen(false);
                        router.reload({ only: ['deposits'] });
                    },
                    onFinish: () => setSubmitting(false),
                },
            );
        } else {
            router.post(
                '/fixed-deposits',
                {
                    bank,
                    accountno,
                    principal_amt,
                    maturity_amt,
                    start_date,
                    maturity_date,
                    term: termVal,
                    int_rate,
                    Int_amt,
                    Int_year,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setOpen(false);
                        router.reload({ only: ['deposits'] });
                    },
                    onFinish: () => setSubmitting(false),
                },
            );
        }
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{isEdit ? 'Edit Fixed Deposit' : 'Add New Fixed Deposit'}</DialogTitle>
            </DialogHeader>
            <form className="space-y-4" onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label>Bank</Label>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                            <Select value={bank} onValueChange={setBank}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Bank" />
                                </SelectTrigger>
                                <SelectContent>
                                    {banks.map((b) => (
                                        <SelectItem key={b.id} value={b.name}>
                                            {b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="button" size="sm" onClick={() => setAddBankModal(true)}>
                                + Add Bank
                            </Button>
                        </div>
                        {(clientErrors.bank || errors?.bank) && <div className="mt-1 text-xs text-red-500">{clientErrors.bank || errors?.bank}</div>}
                        {/* Add Bank Modal */}
                        {addBankModal && (
                            <Dialog open={addBankModal} onOpenChange={setAddBankModal}>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Add New Bank</DialogTitle>
                                    </DialogHeader>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                        <Label>Bank Name</Label>
                                        <Input value={newBankName} onChange={e => setNewBankName(e.target.value)} placeholder="Enter bank name" />
                                        {addBankError && <div className="text-xs text-red-500">{addBankError}</div>}
                                    </div>
                                    <DialogFooter>
                                        <Button type="button" variant="secondary" onClick={() => { setAddBankModal(false); setNewBankName(''); setAddBankError(''); }}>Cancel</Button>
                                        <Button type="button" onClick={() => {
                                            if (!newBankName.trim()) {
                                                setAddBankError('Bank name is required');
                                                return;
                                            }
                                            onAddBank(newBankName.trim());
                                            setAddBankModal(false);
                                            setNewBankName('');
                                            setAddBankError('');
                                        }}>Add</Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                    <div>
                        <Label>Account No</Label>
                        <Input value={accountno} onChange={(e) => setAccountno(e.target.value)} placeholder="Account Number" />
                        {(clientErrors.accountno || errors?.accountno) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.accountno || errors?.accountno}</div>
                        )}
                    </div>
                    <div>
                        <Label>Principal Amount</Label>
                        <Input type="number" value={principal_amt} onChange={(e) => setPrincipalAmt(e.target.value)} placeholder="Principal Amount" />
                        {(clientErrors.principal_amt || errors?.principal_amt) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.principal_amt || errors?.principal_amt}</div>
                        )}
                    </div>
                    <div>
                        <Label>Maturity Amount</Label>
                        <Input type="number" value={maturity_amt} onChange={(e) => setMaturityAmt(e.target.value)} placeholder="Maturity Amount" />
                        {(clientErrors.maturity_amt || errors?.maturity_amt) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.maturity_amt || errors?.maturity_amt}</div>
                        )}
                    </div>
                    <div>
                        <Label>Start Date</Label>
                        <Input
                            value={start_date}
                            onChange={e => setStartDate(e.target.value)}
                            type="date"
                            disabled={submitting}
                        />
                        {(clientErrors.start_date || errors?.start_date) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.start_date || errors?.start_date}</div>
                        )}
                    </div>
                    <div>
                        <Label>Maturity Date</Label>
                        <Input
                            value={maturity_date}
                            onChange={e => setMaturityDate(e.target.value)}
                            type="date"
                            disabled={submitting}
                        />
                        {(clientErrors.maturity_date || errors?.maturity_date) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.maturity_date || errors?.maturity_date}</div>
                        )}
                    </div>
                    <div>
                        <Label>Term (days)</Label>
                        <Input
                            value={term}
                            placeholder="Term in days"
                            type="text"
                            inputMode="numeric"
                            disabled
                        />
                        {(clientErrors.term || errors?.term) && <div className="mt-1 text-xs text-red-500">{clientErrors.term || errors?.term}</div>}
                    </div>
                    <div>
                        <Label>Interest Rate (%)</Label>
                        <Input type="number" value={int_rate} onChange={(e) => setIntRate(e.target.value)} placeholder="Interest Rate" />
                        {(clientErrors.int_rate || errors?.int_rate) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.int_rate || errors?.int_rate}</div>
                        )}
                    </div>
                    <div>
                        <Label>Interest Amount</Label>
                        <Input value={Int_amt.toFixed(2)} readOnly />
                    </div>
                    <div>
                        <Label>Interest per Year</Label>
                        <Input value={Int_year} readOnly />
                    </div>
                </div>
                <DialogFooter className="pt-4">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" disabled={submitting}>
                        {submitting ? (isEdit ? 'Saving...' : 'Saving...') : isEdit ? 'Save Changes' : 'Save Deposit'}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
};

function formatINR(amount: number) {
    return '₹ ' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function formatINRWholeNumber(amount: number) {
    return '₹ ' + Number(amount).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

const sortDirections = {
    asc: 'asc',
    desc: 'desc',
};

const columns = [
    { key: 'bank', label: 'Bank Name' },
    { key: 'accountno', label: 'Account No' },
    { key: 'principal_amt', label: 'Principal Amount' },
    { key: 'maturity_amt', label: 'Maturity Amount' },
    { key: 'maturity_date', label: 'Maturity Date' },
    { key: 'start_date', label: 'Start Date' },
    { key: 'term', label: 'Term' },
    { key: 'int_rate', label: 'Interest %' },
    { key: 'Int_amt', label: 'Interest Amount' },
    { key: 'Int_year', label: 'Interest Year' },
    { key: 'days_balance', label: 'Days Balance' },
];

// An FD is archived if closed or matured, otherwise active
const isArchived = (fd: any) => !!fd?.closed || !!fd?.matured; // Use truthiness check after casting
const isActive = (fd: any) => !isArchived(fd);

const FixedDepositTable = ({ deposits, sortKey, sortDir, onSort, onEdit, onClose, onMature, showArchived }: any) => {
    // Calculate totals for the specified columns
    const totals = deposits.reduce((acc: any, fd: any) => {
        acc.principal_amt += Number(fd.principal_amt) || 0;
        acc.maturity_amt += Number(fd.maturity_amt) || 0;
        acc.Int_amt += Number(fd.Int_amt) || 0;
        acc.Int_year += Number(fd.Int_year) || 0;
        return acc;
    }, {
        principal_amt: 0,
        maturity_amt: 0,
        Int_amt: 0,
        Int_year: 0
    });

    return (
        <div className="mb-6 hidden w-full overflow-x-auto md:block">
            <table className="min-w-full divide-y divide-gray-200 rounded-lg bg-white text-xs shadow md:text-sm">
                <thead className="bg-gray-100">
                    <tr>
                        {columns.map((col) => {
                            if (showArchived && col.key === 'days_balance') {
                                return null;
                            }
                            return (
                                <th
                                    key={col.key}
                                    className="cursor-pointer px-2 py-2 text-left font-semibold select-none"
                                    onClick={() => onSort(col.key)}
                                >
                                    {col.label}
                                    {sortKey === col.key && <span className="ml-1">{sortDir === 'asc' ? '▲' : '▼'}</span>}
                                </th>
                            );
                        })}
                        {!showArchived && <th className="px-2 py-2">Actions</th>}
                        {showArchived && <th className="px-2 py-2 text-left font-semibold">Status</th>}
                    </tr>
                </thead>
                <tbody>
                    {deposits.map((fd: any, idx: number) => (
                        <tr key={fd.id} className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                            <td className="px-2 py-2 whitespace-nowrap">{fd.bank}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{fd.accountno}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{formatINR(fd.principal_amt)}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{formatINR(fd.maturity_amt)}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{new Date(fd.maturity_date).toLocaleDateString('en-GB')}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{new Date(fd.start_date).toLocaleDateString('en-GB')}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{fd.term}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{Number(fd.int_rate).toFixed(2)}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{formatINR(fd.Int_amt)}</td>
                            <td className="px-2 py-2 whitespace-nowrap">{formatINR(fd.Int_year)}</td>
                            {!showArchived && <td className="px-2 py-2 whitespace-nowrap">{renderDaysBalanceBadge(fd.maturity_date)}</td>}
                            {!showArchived && (
                                <td className="flex gap-2 px-2 py-2 whitespace-nowrap">
                                    {isActive(fd) && (
                                        <>
                                            <Button size="icon" variant="ghost" onClick={() => onEdit(fd)} title="Edit">
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button size="icon" variant="ghost" onClick={() => onClose(fd)} title="Close">
                                                <XCircle className="h-4 w-4 text-red-500" />
                                            </Button>
                                            {(() => {
                                                const today = new Date();
                                                const maturity = new Date(fd.maturity_date);
                                                today.setHours(0, 0, 0, 0);
                                                if (today >= maturity) {
                                                    return (
                                                        <Button size="icon" variant="ghost" onClick={() => onMature(fd)} title="Matured">
                                                            <CheckCircle className="h-4 w-4 text-green-600" />
                                                        </Button>
                                                    );
                                                }
                                                return null;
                                            })()}
                                        </>
                                    )}
                                </td>
                            )}
                            {showArchived && (
                                <td className="px-2 py-2 whitespace-nowrap">
                                    {fd.matured ? (
                                        <Badge variant="default" className="bg-green-600 text-white hover:bg-green-700">
                                            Matured
                                        </Badge>
                                    ) : fd.closed ? (
                                        <Badge variant="secondary">Closed</Badge>
                                    ) : null}
                                </td>
                            )}
                        </tr>
                    ))}
                    {/* Totals Row */}
                    <tr className="bg-gray-200 border-t-2 border-gray-400">
                        <td className="px-2 py-2 whitespace-nowrap font-bold">TOTAL</td>
                        <td className="px-2 py-2 whitespace-nowrap"></td>
                        <td className="px-2 py-2 whitespace-nowrap font-bold">{formatINRWholeNumber(totals.principal_amt)}</td>
                        <td className="px-2 py-2 whitespace-nowrap font-bold">{formatINRWholeNumber(totals.maturity_amt)}</td>
                        <td className="px-2 py-2 whitespace-nowrap"></td>
                        <td className="px-2 py-2 whitespace-nowrap"></td>
                        <td className="px-2 py-2 whitespace-nowrap"></td>
                        <td className="px-2 py-2 whitespace-nowrap"></td>
                        <td className="px-2 py-2 whitespace-nowrap font-bold">{formatINRWholeNumber(totals.Int_amt)}</td>
                        <td className="px-2 py-2 whitespace-nowrap font-bold">{formatINRWholeNumber(totals.Int_year)}</td>
                        {!showArchived && <td className="px-2 py-2 whitespace-nowrap"></td>}
                        {!showArchived && <td className="px-2 py-2 whitespace-nowrap"></td>}
                        {showArchived && <td className="px-2 py-2 whitespace-nowrap"></td>}
                    </tr>
                </tbody>
            </table>
        </div>
    );
};

const FixedDepositCards = ({ deposits, onEdit, onClose, onMature, showArchived }: any) => {
    return (
        <div className="mb-6 flex flex-col gap-4 md:hidden">
            {deposits.map((fd: any, idx: number) => (
                <Card key={fd.id} className="w-full border border-gray-200 shadow">
                    <CardHeader className="pb-2">
                        <div className="flex flex-col md:flex-row md:items-center md:gap-4">
                            <CardTitle className="flex min-w-[180px] items-center gap-2">
                                {fd.bank} <span className="text-xs font-normal text-gray-400">({fd.accountno})</span>
                            </CardTitle>
                            {showArchived && (
                                <div className="md:ml-auto">
                                    {fd.matured ? (
                                        <Badge variant="default" className="bg-green-600 text-white hover:bg-green-700">
                                            Matured
                                        </Badge>
                                    ) : fd.closed ? (
                                        <Badge variant="secondary">Closed</Badge>
                                    ) : null}
                                </div>
                            )}
                            {!showArchived && (
                                <CardDescription className="md:ml-auto">
                                    Term: {fd.term} days | Interest: {Number(fd.int_rate).toFixed(2)}%
                                </CardDescription>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex w-full flex-col text-sm md:flex-row md:items-center md:gap-6">
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Principal:</span> {formatINR(fd.principal_amt)}
                            </div>
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Maturity:</span> {formatINR(fd.maturity_amt)}
                            </div>
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Start Date:</span> {new Date(fd.start_date).toLocaleDateString('en-GB')}
                            </div>
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Maturity Date:</span> {new Date(fd.maturity_date).toLocaleDateString('en-GB')}
                            </div>
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Interest Amount:</span> {formatINR(fd.Int_amt)}
                            </div>
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Interest/Year:</span> {formatINR(fd.Int_year)}
                            </div>
                            {!showArchived && (
                                <div className="flex-1 md:w-1/6">
                                    <span className="font-medium">Days Balance:</span> {renderDaysBalanceBadge(fd.maturity_date)}
                                </div>
                            )}
                        </div>
                        {!showArchived && (
                            <div className="mt-2 flex gap-2">
                                {isActive(fd) && (
                                    <>
                                        <Button size="icon" variant="ghost" onClick={() => onEdit(fd)} title="Edit">
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" onClick={() => onClose(fd)} title="Close">
                                            <XCircle className="h-4 w-4 text-red-500" />
                                        </Button>
                                        {(() => {
                                            const today = new Date();
                                            const maturity = new Date(fd.maturity_date);
                                            today.setHours(0, 0, 0, 0);
                                            if (today >= maturity) {
                                                return (
                                                    <Button size="icon" variant="ghost" onClick={() => onMature(fd)} title="Matured">
                                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                                    </Button>
                                                );
                                            }
                                            return null;
                                        })()}
                                    </>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
};

const BankSummaryCards = ({ deposits }: any) => {
    // Group deposits by bank
    const banks = Array.from(new Set(deposits.map((d: any) => d.bank)));
    const formatINR = (amount: number) => '₹ ' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });

    // Calculate summary for each bank
    const summaries = banks.map((bank) => {
        const bankDeposits = deposits.filter((d: any) => d.bank === bank);
        const totalPrincipal = bankDeposits.reduce((sum: number, d: any) => sum + Number(d.principal_amt), 0);
        const totalMaturity = bankDeposits.reduce((sum: number, d: any) => sum + Number(d.maturity_amt), 0);
        const totalInterest = bankDeposits.reduce((sum: number, d: any) => sum + Number(d.Int_amt), 0);
        return {
            bank,
            count: bankDeposits.length,
            totalPrincipal,
            totalMaturity,
            totalInterest,
        };
    });

    return (
        <div className="mb-6 flex flex-wrap gap-4">
            {summaries.map((summary) => (
                <Card
                    key={String(summary.bank)}
                    className="min-w-[220px] flex-1 border border-gray-200 bg-gradient-to-br from-blue-50 to-white shadow"
                >
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg">{String(summary.bank)}</CardTitle>
                        <CardDescription>{String(summary.count)} Deposits</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <div>
                            <span className="font-medium">Total Principal:</span> {formatINR(summary.totalPrincipal)}
                        </div>
                        <div>
                            <span className="font-medium">Total Maturity:</span> {formatINR(summary.totalMaturity)}
                        </div>
                        <div>
                            <span className="font-medium">Total Interest:</span> {formatINR(summary.totalInterest)}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
};

const FixedDepositsPage = () => {
    const { deposits, banks: banksProp } = usePage().props as any;
    const [banks, setBanks] = useState(banksProp || []);

    // Add new bank handler
    const handleAddBank = async (name: string) => {
        try {
            const response = await fetch('/banks', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ name }),
            });
            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to add bank');
            }
            const newBank = await response.json();
            setBanks((prev: any) => [...prev, newBank]);
        } catch (err: any) {
            alert(err.message || 'Failed to add bank');
        }
    };

    // Sorting state
    const [sortKey, setSortKey] = useState('maturity_date');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
    const [modalOpen, setModalOpen] = useState(false);
    const [bankBalanceModalOpen, setBankBalanceModalOpen] = useState(false);
    const [editFD, setEditFD] = useState<any>(null);
    const [closeFD, setCloseFD] = useState<any>(null);
    const [matureFD, setMatureFD] = useState<any>(null);
    const [showMatured, setShowMatured] = useState(false);

    // Filter deposits based on toggle - Use explicit boolean check
    const filteredDeposits = deposits.filter((fd: any) => (showMatured ? isArchived(fd) : isActive(fd)));

    // Sort deposits
    const sortedDeposits = [...filteredDeposits].sort((a, b) => {
        let aVal = a[sortKey];
        let bVal = b[sortKey];
        if (sortKey === 'maturity_date' || sortKey === 'start_date') {
            aVal = new Date(aVal).getTime();
            bVal = new Date(bVal).getTime();
        }
        if (aVal < bVal) return sortDir === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDir === 'asc' ? 1 : -1;
        return 0;
    });

    const handleSort = (key: string) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    };

    // Action handlers
    const handleEdit = (fd: any) => setEditFD(fd);
    const handleClose = (fd: any) => setCloseFD(fd);
    const handleMature = (fd: any) => setMatureFD(fd);

    // Close FD logic
    const handleConfirmClose = (fdToClose: any) => {
        const start = new Date(fdToClose.start_date);
        const today = new Date();
        const days = Math.ceil((today.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));

        let payload: any = {};
        if (days < 365) {
            payload.Int_amt = 0;
            payload.Int_year = 0;
        }
        // Backend will set 'closed = true'. We only send interest adjustments.

        router.put(`/fixed-deposits/${fdToClose.id}/close`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setCloseFD(null); // Close modal first
                router.reload({ only: ['deposits'] }); // Then reload relevant props
            },
            onError: (errors) => {
                // Optional: Handle errors
                console.error('Failed to close FD:', errors);
            },
            // onFinish: () => { // onFinish can be removed if onSuccess/onError handle modal state
            // setCloseFD(null); // Now handled in onSuccess
            // },
        });
    };

    // Mature FD logic
    const handleConfirmMature = (fdToMature: any) => {
        router.put(
            `/fixed-deposits/${fdToMature.id}/mature`,
            {}, // Minimal payload, backend handles setting 'matured'
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMatureFD(null); // Close modal first
                    router.reload({ only: ['deposits'] }); // Then reload relevant props
                },
                onError: (errors) => {
                    // Optional: Handle errors
                    console.error('Failed to mature FD:', errors);
                },
                // onFinish: () => { // onFinish can be removed if onSuccess/onError handle modal state
                //    setMatureFD(null); // Now handled in onSuccess
                // },
            },
        );
    };

    return (
        <div className="p-4">
            {/* Add FD button/modal at the top */}
            <div className="mb-4 flex gap-2">
                <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                    <DialogTrigger asChild>
                        <Button variant="default">
                            + Add New Deposit
                        </Button>
                    </DialogTrigger>
                    <AddDepositModal open={modalOpen} setOpen={setModalOpen} banks={banks} onAddBank={handleAddBank} />
                </Dialog>
                
                <Dialog open={bankBalanceModalOpen} onOpenChange={setBankBalanceModalOpen}>
                    <DialogTrigger asChild>
                        <Button variant="outline">
                            Update Bank Balance
                        </Button>
                    </DialogTrigger>
                    <BankBalanceModal open={bankBalanceModalOpen} setOpen={setBankBalanceModalOpen} banks={banks} />
                </Dialog>
            </div>
            {/* Bank summary cards below Add FD */}
            <BankSummaryCards deposits={sortedDeposits} />
            {/* Toggle Switch for Active/Archived below cards */}
            <div className="mb-4 flex items-center gap-2">
                <Switch id="toggle-matured" checked={showMatured} onCheckedChange={setShowMatured} />
                <label htmlFor="toggle-matured" className="cursor-pointer text-sm font-medium select-none">
                    {showMatured ? 'Show Archived FDs' : 'Show Active FDs'}
                </label>
            </div>
            {/* Edit Modal */}
            {editFD && (
                <Dialog open={!!editFD} onOpenChange={() => setEditFD(null)}>
                    <AddDepositModal open={!!editFD} setOpen={() => setEditFD(null)} fd={editFD} isEdit banks={banks} onAddBank={handleAddBank} />
                </Dialog>
            )}
            {/* Close Modal */}
            {closeFD && (
                <Dialog open={!!closeFD} onOpenChange={() => setCloseFD(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Close Fixed Deposit</DialogTitle>
                        </DialogHeader>
                        <div>
                            {(() => {
                                const start = new Date(closeFD.start_date);
                                const today = new Date();
                                const days = Math.ceil((today.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));
                                if (days < 365) {
                                    return (
                                        <div className="mb-4 text-red-600">
                                            No interest will be paid as FD is closed within 1 year. Are you sure you want to close?
                                        </div>
                                    );
                                } else {
                                    return <div className="mb-4">You can update the FD details before closing. Are you sure you want to close?</div>;
                                }
                            })()}
                        </div>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="button" onClick={() => handleConfirmClose(closeFD)}>
                                Confirm Close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
            {/* Mature Modal */}
            {matureFD && (
                <Dialog open={!!matureFD} onOpenChange={() => setMatureFD(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Mature Fixed Deposit</DialogTitle>
                        </DialogHeader>
                        <div>Mark this FD as matured?</div>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="button" onClick={() => handleConfirmMature(matureFD)}>
                                Mark as Matured
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
            {/* Table View - Pass showMatured prop */}
            <FixedDepositTable
                deposits={sortedDeposits}
                sortKey={sortKey}
                sortDir={sortDir}
                onSort={handleSort}
                onEdit={handleEdit}
                onClose={handleClose}
                onMature={handleMature}
                showArchived={showMatured}
            />
            {/* Card View - Pass showMatured prop */}
            <FixedDepositCards
                deposits={sortedDeposits}
                onEdit={handleEdit}
                onClose={handleClose}
                onMature={handleMature}
                showArchived={showMatured}
            />
        </div>
    );
};

// Assign the layout
FixedDepositsPage.layout = (page: any) => <AppSidebarLayout breadcrumbs={[{ title: 'Fixed Deposits', href: '/fixed-deposits' }]} children={page} />;

export default FixedDepositsPage;
