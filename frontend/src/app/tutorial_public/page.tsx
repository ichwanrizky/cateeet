"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ThemeToggle } from "@/components/theme-toggle";

const steps = [
  {
    number: 1,
    title: "Cari bot di Telegram",
    description:
      "Buka Telegram dan cari @MoneyTrackerWanBot di kolom pencarian.",
    image: "/tutorial_bot/1.jpeg",
  },
  {
    number: 2,
    title: "Buka chat bot",
    description:
      "Tap pada bot yang muncul di hasil pencarian, lalu tap tombol Start untuk memulai.",
    image: "/tutorial_bot/2.jpeg",
  },
  {
    number: 3,
    title: "Daftar & hubungkan akun",
    description:
      "Daftar di aplikasi web ini, lalu update Telegram ID kamu di menu Telegram. Setelah terhubung, bot akan mengenali akunmu.",
    image: "/tutorial_bot/3.jpeg",
    note: "Sebelum Telegram ID terhubung, bot akan membalas pesan panduan registrasi beserta Telegram ID kamu.",
  },
  {
    number: 4,
    title: "Catat pemasukan",
    description:
      "Kirim pesan dengan format: deskripsi jumlah + wallet. Contoh: freelance 500rb + bni",
    image: "/tutorial_bot/5.jpeg",
    code: "freelance 500rb + bni",
  },
  {
    number: 5,
    title: "Catat pengeluaran",
    description:
      "Format sama tapi gunakan tanda minus (-). Contoh: bensin 30 - bni",
    image: "/tutorial_bot/6.jpeg",
    code: "bensin 30 - bni",
    note: "Angka tanpa satuan otomatis dikalikan 1.000. Jadi 30 = Rp 30.000",
  },
  {
    number: 6,
    title: "Tentukan kategori manual",
    description:
      "Tambahkan #nama_kategori di akhir pesan untuk menentukan kategori secara manual. Bot akan mencocokkan dengan kategori yang sudah kamu buat.",
    image: "/tutorial_bot/6.jpeg",
    code: "coffee 50 - bni #jajan\nnasi padang 25 - cash #makan",
    note: "Tanpa #tag, bot akan otomatis mendeteksi kategori dari deskripsi transaksi.",
  },
  {
    number: 7,
    title: "Nama wallet fleksibel",
    description:
      "Tidak perlu ketik nama wallet lengkap — cukup sebagian saja, bot akan mencocokkan otomatis.",
    image: "/tutorial_bot/5.jpeg",
    code: "bensin 50 - bni pol\ngaji 5jt + bsi sy",
    note: "Contoh: 'bni pol' akan match ke wallet 'BNI Polibatam'.",
  },
  {
    number: 8,
    title: "Transfer antar wallet — format ke / -",
    description: "Pindahkan dana antar wallet dengan format tf atau transfer.",
    image: "/tutorial_bot/9.jpeg",
    code: "tf 10 bni - cash\ntf 10rb bni ke cash",
  },
  {
    number: 9,
    title: "Transfer antar wallet — format spasi",
    description: "Bisa juga tanpa pemisah, langsung nama wallet tujuan.",
    image: "/tutorial_bot/10.jpeg",
    code: "tf 10rb bni cash",
  },
  {
    number: 10,
    title: "Transfer dengan biaya admin",
    description: "Tambahkan biaya admin di akhir pesan.",
    image: "/tutorial_bot/11.jpeg",
    code: "tf 50 bni cash 2500",
    note: "2500 = Rp 2.500 (bukan Rp 2.500.000). Biaya admin dipotong dari wallet asal.",
  },
  {
    number: 11,
    title: "Cek transaksi harian",
    description:
      "Kirim /hari untuk melihat semua transaksi hari ini. Transaksi transfer ditandai 🔄 dan tidak dihitung di total.",
    image: "/tutorial_bot/7.jpeg",
    code: "/hari\n/hari 25/3/2026",
  },
  {
    number: 12,
    title: "Cek saldo wallet",
    description:
      "Kirim /saldo untuk melihat saldo semua wallet kamu beserta total keseluruhan.",
    image: "/tutorial_bot/8.jpeg",
    code: "/saldo",
  },
];

const formats = [
  { input: "30", result: "Rp 30.000" },
  { input: "30rb", result: "Rp 30.000" },
  { input: "30k", result: "Rp 30.000" },
  { input: "30.5", result: "Rp 30.500" },
  { input: "5jt", result: "Rp 5.000.000" },
  { input: "500perak", result: "Rp 500" },
  { input: "30600", result: "Rp 30.600" },
];

export default function TutorialPublicPage() {
  const [lightbox, setLightbox] = useState<string | null>(null);

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b bg-card sticky top-0 z-40">
        <div className="max-w-4xl mx-auto px-4 h-14 flex items-center justify-between">
          <span className="font-semibold">💸 Cateeet</span>
          <div className="flex items-center gap-2">
            <ThemeToggle />
            <Link href="/login">
              <Button size="sm">Masuk</Button>
            </Link>
            <Link href="/register">
              <Button size="sm" variant="outline">
                Daftar
              </Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="max-w-4xl mx-auto px-4 py-8 space-y-8">
        <div>
          <h1 className="text-2xl font-semibold">Tutorial Penggunaan Bot</h1>
          <p className="text-sm text-muted-foreground">
            Panduan lengkap cara menggunakan Cateeet via Telegram
          </p>
        </div>

        {/* Feature highlight */}
        <div className="rounded-lg border bg-gradient-to-br from-primary/5 to-primary/10 p-6 space-y-3">
          <div className="flex items-center gap-2">
            <span className="text-2xl">👨‍👩‍👧‍👦</span>
            <h2 className="font-semibold text-lg">
              Kelola keuangan bareng keluarga
            </h2>
          </div>
          <p className="text-sm text-muted-foreground leading-relaxed">
            Cateeet bukan cuma untuk pribadi — kamu bisa membuat{" "}
            <strong>grup keluarga</strong> dan mencatat keuangan bersama. Suami
            istri, orang tua dan anak, atau siapa saja bisa bergabung dalam satu
            grup dan melihat transaksi satu sama lain secara real-time.
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
            <div className="rounded-md bg-background/60 border px-3 py-2 text-center">
              <p className="text-lg">📊</p>
              <p className="text-sm font-medium mt-1">Transaksi bersama</p>
              <p className="text-xs text-muted-foreground mt-0.5">
                Lihat pengeluaran seluruh anggota keluarga
              </p>
            </div>
            <div className="rounded-md bg-background/60 border px-3 py-2 text-center">
              <p className="text-lg">👛</p>
              <p className="text-sm font-medium mt-1">Wallet bersama</p>
              <p className="text-xs text-muted-foreground mt-0.5">
                Share wallet ke anggota keluarga
              </p>
            </div>
            <div className="rounded-md bg-background/60 border px-3 py-2 text-center">
              <p className="text-lg">🔒</p>
              <p className="text-sm font-medium mt-1">Privasi terjaga</p>
              <p className="text-xs text-muted-foreground mt-0.5">
                Keluar dari grup kapan saja, data langsung tersembunyi
              </p>
            </div>
          </div>
        </div>

        {/* Format amount */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">📊 Format Nominal</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {formats.map((f) => (
                <div
                  key={f.input}
                  className="rounded-md border bg-muted/50 px-3 py-2 text-center"
                >
                  <p className="font-mono font-medium text-sm">{f.input}</p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    {f.result}
                  </p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Format transaksi */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              💬 Format Pesan Transaksi
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="rounded-md bg-muted px-4 py-3 font-mono text-sm space-y-1">
              <p className="text-muted-foreground"># Dengan tanggal</p>
              <p>26/3</p>
              <p>makan siang 30 - bni</p>
              <p>gaji 5jt + bsi</p>
            </div>
            <div className="rounded-md bg-muted px-4 py-3 font-mono text-sm space-y-1">
              <p className="text-muted-foreground">
                # Tanpa tanggal (default hari ini)
              </p>
              <p>bensin 30 - cash</p>
              <p>freelance 500rb + bni</p>
            </div>
            <div className="rounded-md bg-muted px-4 py-3 font-mono text-sm space-y-1">
              <p className="text-muted-foreground"># Dengan kategori manual</p>
              <p>coffee 50 - bni #jajan</p>
              <p>nasi padang 25 - cash #makan</p>
            </div>
            <div className="grid grid-cols-2 gap-2 text-sm">
              <div className="rounded-md border px-3 py-2">
                <p className="font-medium mb-1">Pengeluaran</p>
                <p className="font-mono text-muted-foreground">
                  deskripsi jumlah - wallet
                </p>
              </div>
              <div className="rounded-md border px-3 py-2">
                <p className="font-medium mb-1">Pemasukan</p>
                <p className="font-mono text-muted-foreground">
                  deskripsi jumlah + wallet
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Steps */}
        <div className="space-y-6">
          {steps.map((step) => (
            <Card key={step.number}>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-3">
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-sm font-medium">
                    {step.number}
                  </span>
                  {step.title}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                  <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                      {step.description}
                    </p>
                    {step.code && (
                      <div className="rounded-md bg-muted px-4 py-3 font-mono text-sm whitespace-pre">
                        {step.code}
                      </div>
                    )}
                    {step.note && (
                      <div className="flex gap-2 rounded-md bg-amber-500/10 border border-amber-500/20 px-3 py-2">
                        <span className="text-amber-500 shrink-0">💡</span>
                        <p className="text-xs text-amber-600 dark:text-amber-400">
                          {step.note}
                        </p>
                      </div>
                    )}
                  </div>
                  <div className="flex justify-center md:justify-end">
                    <button
                      onClick={() => setLightbox(step.image)}
                      className="relative w-48 overflow-hidden rounded-2xl border shadow-sm hover:opacity-90 transition-opacity cursor-zoom-in"
                    >
                      <Image
                        src={step.image}
                        alt={step.title}
                        width={300}
                        height={600}
                        className="w-full h-auto object-cover"
                      />
                    </button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Commands */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">⌨️ Daftar Command</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {[
                { cmd: "/start", desc: "Mulai bot & lihat panduan singkat" },
                { cmd: "/help", desc: "Panduan format transaksi lengkap" },
                { cmd: "/saldo", desc: "Lihat semua saldo wallet" },
                { cmd: "/hari", desc: "Transaksi hari ini" },
                { cmd: "/hari 26/3/2026", desc: "Transaksi tanggal tertentu" },
              ].map((c) => (
                <div
                  key={c.cmd}
                  className="flex items-center gap-3 rounded-md border px-4 py-2"
                >
                  <Badge variant="secondary" className="font-mono shrink-0">
                    {c.cmd}
                  </Badge>
                  <p className="text-sm text-muted-foreground">{c.desc}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* CTA */}
        <div className="rounded-lg border bg-card p-6 text-center space-y-3">
          <p className="font-semibold">Siap mulai mencatat keuangan?</p>
          <p className="text-sm text-muted-foreground">
            Daftar sekarang dan mulai gunakan Cateeet bot.
          </p>
          <div className="flex gap-2 justify-center">
            <Link href="/register">
              <Button>Daftar Sekarang</Button>
            </Link>
            <Link href="/login">
              <Button variant="outline">Masuk</Button>
            </Link>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t py-4 text-center text-xs text-muted-foreground mt-8">
        Developed by{" "}
        <span className="font-medium text-foreground">Ichwan Rizky</span> ·{" "}
        {new Date().getFullYear()}
      </footer>

      {/* Lightbox */}
      {lightbox && (
        <div
          className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
          onClick={() => setLightbox(null)}
        >
          <div className="relative max-w-sm w-full">
            <Image
              src={lightbox}
              alt="Preview"
              width={400}
              height={800}
              className="w-full h-auto rounded-2xl"
            />
            <p className="text-center text-xs text-white/60 mt-2">
              Tap di mana saja untuk menutup
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
