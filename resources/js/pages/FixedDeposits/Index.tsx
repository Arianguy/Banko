import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const BANKS = [
    { value: 'SBI', label: 'SBI' },
    { value: 'ICICI', label: 'ICICI' },
    { value: 'HDFC', label: 'HDFC' },
];

function getDaysBalance(maturity_date: string) {
    const today = new Date();
    const maturity = new Date(maturity_date);
    const diff = maturity.getTime() - today.setHours(0, 0, 0, 0);
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

const AddDepositModal = ({ open, setOpen }: { open: boolean; setOpen: (v: boolean) => void }) => {
    const [bank, setBank] = useState('SBI');
    const [accountno, setAccountno] = useState('');
    const [principal_amt, setPrincipalAmt] = useState('');
    const [maturity_amt, setMaturityAmt] = useState('');
    const [start_date, setStartDate] = useState('');
    const [maturity_date, setMaturityDate] = useState('');
    const [int_rate, setIntRate] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [clientErrors, setClientErrors] = useState<any>({});
    const { errors } = usePage().props as any;

    // Calculate term (days) from start_date and maturity_date
    let term = '';
    if (start_date && maturity_date) {
        const start = new Date(start_date);
        const end = new Date(maturity_date);
        const diff = end.getTime() - start.getTime();
        term = diff > 0 ? Math.ceil(diff / (1000 * 60 * 60 * 24)).toString() : '';
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
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add New Fixed Deposit</DialogTitle>
            </DialogHeader>
            <form className="space-y-4" onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label>Bank</Label>
                        <Select value={bank} onValueChange={setBank}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select bank" />
                            </SelectTrigger>
                            <SelectContent>
                                {BANKS.map((b) => (
                                    <SelectItem key={b.value} value={b.value}>
                                        {b.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {(clientErrors.bank || errors?.bank) && <div className="mt-1 text-xs text-red-500">{clientErrors.bank || errors?.bank}</div>}
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
                        <Input type="date" value={start_date} onChange={(e) => setStartDate(e.target.value)} />
                        {(clientErrors.start_date || errors?.start_date) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.start_date || errors?.start_date}</div>
                        )}
                    </div>
                    <div>
                        <Label>Maturity Date</Label>
                        <Input type="date" value={maturity_date} onChange={(e) => setMaturityDate(e.target.value)} />
                        {(clientErrors.maturity_date || errors?.maturity_date) && (
                            <div className="mt-1 text-xs text-red-500">{clientErrors.maturity_date || errors?.maturity_date}</div>
                        )}
                    </div>
                    <div>
                        <Label>Term (days)</Label>
                        <Input value={term} readOnly placeholder="Term in days" />
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
                        {submitting ? 'Saving...' : 'Save Deposit'}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
};

function formatINR(amount: number) {
    return '₹ ' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
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

const FixedDepositTable = ({ deposits, sortKey, sortDir, onSort }: any) => {
    return (
        <div className="mb-6 hidden w-full overflow-x-auto md:block">
            <table className="min-w-full divide-y divide-gray-200 rounded-lg bg-white text-xs shadow md:text-sm">
                <thead className="bg-gray-100">
                    <tr>
                        {columns.map((col) => (
                            <th
                                key={col.key}
                                className="cursor-pointer px-2 py-2 text-left font-semibold select-none"
                                onClick={() => onSort(col.key)}
                            >
                                {col.label}
                                {sortKey === col.key && <span className="ml-1">{sortDir === 'asc' ? '▲' : '▼'}</span>}
                            </th>
                        ))}
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
                            <td className="px-2 py-2 whitespace-nowrap">{getDaysBalance(fd.maturity_date)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

const FixedDepositCards = ({ deposits }: any) => {
    return (
        <div className="mb-6 flex flex-col gap-4 md:hidden">
            {deposits.map((fd: any, idx: number) => (
                <Card key={fd.id} className="w-full border border-gray-200 shadow">
                    <CardHeader className="pb-2">
                        <div className="flex flex-col md:flex-row md:items-center md:gap-4">
                            <CardTitle className="flex min-w-[180px] items-center gap-2">
                                {fd.bank} <span className="text-xs font-normal text-gray-400">({fd.accountno})</span>
                            </CardTitle>
                            <CardDescription className="md:ml-auto">
                                Term: {fd.term} days | Interest: {Number(fd.int_rate).toFixed(2)}%
                            </CardDescription>
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
                            <div className="flex-1 md:w-1/6">
                                <span className="font-medium">Days Balance:</span> {getDaysBalance(fd.maturity_date)}
                            </div>
                        </div>
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
                <Card key={summary.bank} className="min-w-[220px] flex-1 border border-gray-200 bg-gradient-to-br from-blue-50 to-white shadow">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg">{summary.bank}</CardTitle>
                        <CardDescription>{summary.count} Deposits</CardDescription>
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
    const { deposits } = usePage().props as any;
    // Sorting state
    const [sortKey, setSortKey] = useState('maturity_date');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
    const [modalOpen, setModalOpen] = useState(false);

    // Sort deposits
    const sortedDeposits = [...deposits].sort((a, b) => {
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

    return (
        <div className="p-4">
            {/* Bank summary cards at the top */}
            <BankSummaryCards deposits={sortedDeposits} />
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogTrigger asChild>
                    <Button className="mb-4" variant="default">
                        + Add New Deposit
                    </Button>
                </DialogTrigger>
                <AddDepositModal open={modalOpen} setOpen={setModalOpen} />
            </Dialog>
            <FixedDepositTable deposits={sortedDeposits} sortKey={sortKey} sortDir={sortDir} onSort={handleSort} />
            <FixedDepositCards deposits={sortedDeposits} />
        </div>
    );
};

// Assign the layout
FixedDepositsPage.layout = (page: any) => <AppSidebarLayout breadcrumbs={[{ title: 'Fixed Deposits', href: '/fixed-deposits' }]} children={page} />;

export default FixedDepositsPage;
