"use client";

import { useState, useEffect, useCallback, Fragment } from "react";
import { useSession } from "next-auth/react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Plus,
  Trash2,
  Pencil,
  ArrowLeftRight,
  ChevronRight,
  ChevronLeft,
} from "lucide-react";
import { TransactionForm } from "./transaction-form";
import { AlertBanner } from "@/components/alert-banner";
import { TransferForm } from "./transfer-form";

type Transaction = {
  id: number;
  description: string;
  amount: number;
  type: "in" | "out";
  date: string;
  created_at: string;
  wallet_name: string;
  category_name: string;
  category_icon: string;
  input_by: string;
  is_transfer: boolean;
  is_balancing: boolean;
};

const LIMIT = 20;

export default function TransactionsPage() {
  const { data: session } = useSession();
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editTransaction, setEditTransaction] = useState<Transaction | null>(
    null,
  );
  const [transferOpen, setTransferOpen] = useState(false);
  const [error, setError] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

  const now = new Date();
  const [filterMonth, setFilterMonth] = useState(
    `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`,
  );

  const jwt = session?.user?.jwt;

  const fetchTransactions = useCallback(
    async (currentPage = 1) => {
      if (!jwt) return;
      setLoading(true);
      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/api/transactions?page=${currentPage}&limit=${LIMIT}&month=${filterMonth}`,
          { headers: { Authorization: `Bearer ${jwt}` } },
        );
        const data = await res.json();
        if (data.success) {
          setTransactions(data.data.transactions);
          setTotalPages(data.data.pagination.pages);
          setTotal(data.data.pagination.total);
        }
      } finally {
        setLoading(false);
      }
    },
    [jwt, filterMonth],
  );

  useEffect(() => {
    fetchTransactions(page);
  }, [fetchTransactions, page]);
  const handleSuccess = () => {
    setPage(1);
    fetchTransactions(1);
  };

  const handleDelete = async () => {
    if (!deleteId || !session?.user?.jwt) return;
    const res = await fetch(
      `${process.env.NEXT_PUBLIC_API_URL}/api/transactions/${deleteId}`,
      {
        method: "DELETE",
        headers: { Authorization: `Bearer ${session.user.jwt}` },
      },
    );
    const data = await res.json();
    if (!data.success) {
      setError(data.message);
    } else {
      handleSuccess();
    }
    setDeleteId(null);
  };

  const formatAmount = (amount: number) =>
    new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
    }).format(amount);

  const grouped = transactions.reduce(
    (acc, trx) => {
      const date = trx.date;
      if (!acc[date]) acc[date] = [];
      acc[date].push(trx);
      return acc;
    },
    {} as Record<string, Transaction[]>,
  );

  const sortedDates = Object.keys(grouped).sort((a, b) => b.localeCompare(a));

  const formatDateHeader = (date: string) => {
    const days = [
      "Minggu",
      "Senin",
      "Selasa",
      "Rabu",
      "Kamis",
      "Jumat",
      "Sabtu",
    ];
    const d = new Date(date);
    return `${days[d.getDay()]}, ${d.toLocaleDateString("id-ID", {
      day: "numeric",
      month: "long",
      year: "numeric",
    })}`;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Transaksi</h1>
          <p className="text-sm text-muted-foreground">
            Riwayat transaksi kamu
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            size="sm"
            variant="outline"
            onClick={() => setTransferOpen(true)}
          >
            <ArrowLeftRight className="h-4 w-4 mr-2" />
            Transfer
          </Button>
          <Button
            size="sm"
            onClick={() => {
              setEditTransaction(null);
              setFormOpen(true);
            }}
          >
            <Plus className="h-4 w-4 mr-2" />
            Tambah
          </Button>
        </div>
      </div>

      <AlertBanner message={error} onClose={() => setError("")} />

      {/* Filter bulan */}
      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="icon"
          className="h-8 w-8"
          onClick={() => {
            const d = new Date(filterMonth + "-01");
            d.setMonth(d.getMonth() - 1);
            setFilterMonth(
              `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`,
            );
            setPage(1);
          }}
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>

        <span className="text-sm font-medium min-w-32 text-center">
          {new Date(filterMonth + "-01").toLocaleDateString("id-ID", {
            month: "long",
            year: "numeric",
          })}
        </span>

        <Button
          variant="outline"
          size="icon"
          className="h-8 w-8"
          onClick={() => {
            const d = new Date(filterMonth + "-01");
            d.setMonth(d.getMonth() + 1);
            setFilterMonth(
              `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`,
            );
            setPage(1);
          }}
          disabled={
            filterMonth >=
            `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`
          }
        >
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Waktu</TableHead>
              <TableHead>Deskripsi</TableHead>
              <TableHead>Kategori</TableHead>
              <TableHead>Wallet</TableHead>
              <TableHead>Oleh</TableHead>
              <TableHead className="text-right">Jumlah</TableHead>
              <TableHead className="w-20" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell
                  colSpan={7}
                  className="text-center py-10 text-muted-foreground"
                >
                  Memuat...
                </TableCell>
              </TableRow>
            ) : sortedDates.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={7}
                  className="text-center py-10 text-muted-foreground"
                >
                  Belum ada transaksi
                </TableCell>
              </TableRow>
            ) : (
              sortedDates.map((date) => (
                <Fragment key={date}>
                  <TableRow className="bg-muted/50 hover:bg-muted/50">
                    <TableCell colSpan={7} className="py-2 px-4">
                      <div className="flex items-center justify-between">
                        <span className="text-sm font-medium">
                          {formatDateHeader(date)}
                        </span>
                        <div className="flex gap-3 text-xs">
                          <span className="text-green-500">
                            +
                            {formatAmount(
                              grouped[date]
                                .filter(
                                  (t) => t.type === "in" && !t.is_transfer,
                                )
                                .reduce((s, t) => s + t.amount, 0),
                            )}
                          </span>
                          <span className="text-destructive">
                            -
                            {formatAmount(
                              grouped[date]
                                .filter(
                                  (t) => t.type === "out" && !t.is_transfer,
                                )
                                .reduce((s, t) => s + t.amount, 0),
                            )}
                          </span>
                        </div>
                      </div>
                    </TableCell>
                  </TableRow>
                  {grouped[date].map((trx) => (
                    <TableRow key={trx.id}>
                      <TableCell className="text-sm text-muted-foreground whitespace-nowrap pl-6">
                        {new Date(trx.created_at).toLocaleTimeString("id-ID", {
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </TableCell>
                      <TableCell className="font-medium">
                        {trx.description}
                      </TableCell>
                      <TableCell>
                        <span className="text-sm text-muted-foreground">
                          {trx.is_balancing
                            ? "⚖️ Balancing"
                            : `${trx.category_icon} ${trx.category_name}`}
                        </span>
                      </TableCell>
                      <TableCell className="text-sm">
                        {trx.wallet_name}
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {trx.input_by}
                      </TableCell>
                      <TableCell className="text-right">
                        <Badge
                          className={
                            trx.type === "in"
                              ? "bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20 hover:bg-green-500/20"
                              : "bg-destructive/10 text-destructive border-destructive/20 hover:bg-destructive/20"
                          }
                          variant="outline"
                        >
                          {trx.type === "in" ? "+" : "-"}
                          {formatAmount(trx.amount)}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1 justify-end">
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            disabled={trx.is_transfer || trx.is_balancing}
                            onClick={() => {
                              setEditTransaction(trx);
                              setFormOpen(true);
                            }}
                          >
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive hover:text-destructive"
                            onClick={() => setDeleteId(trx.id)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </Fragment>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-muted-foreground">
            Total {total} transaksi · Halaman {page} dari {totalPages}
          </p>
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((p) => p - 1)}
              disabled={page === 1 || loading}
            >
              Sebelumnya
            </Button>

            {Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => (
              <Button
                key={p}
                variant={p === page ? "default" : "outline"}
                size="sm"
                className="w-8 h-8 p-0"
                onClick={() => setPage(p)}
                disabled={loading}
              >
                {p}
              </Button>
            ))}

            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((p) => p + 1)}
              disabled={page === totalPages || loading}
            >
              Berikutnya
            </Button>
          </div>
        </div>
      )}

      <AlertDialog open={!!deleteId} onOpenChange={() => setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Hapus transaksi?</AlertDialogTitle>
            <AlertDialogDescription>
              Tindakan ini tidak bisa dibatalkan. Saldo wallet akan
              dikembalikan.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Batal</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Hapus
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <TransactionForm
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={handleSuccess}
        transaction={editTransaction}
      />

      <TransferForm
        open={transferOpen}
        onClose={() => setTransferOpen(false)}
        onSuccess={handleSuccess}
      />
    </div>
  );
}
