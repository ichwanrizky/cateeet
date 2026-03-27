"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { ThemeToggle } from "@/components/theme-toggle";

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({
    username: "",
    display_name: "",
    email: "",
    password: "",
    confirm_password: "",
  });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const generateUsername = (name: string): string => {
    const words = name
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\s]/g, "")
      .split(/\s+/)
      .filter(Boolean);

    if (words.length === 0) return "";
    if (words.length === 1) return words[0].slice(0, 20);

    const skipFirst = words.length > 2 && words[0].length <= 3;
    const start = skipFirst ? 1 : 0;
    const taken = words.slice(start, start + 2);

    return taken.join(".").slice(0, 20);
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setForm((prev) => {
      const updated = { ...prev, [name]: value };
      if (name === "display_name") {
        updated.username = generateUsername(value);
      }
      return updated;
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");

    if (form.password !== form.confirm_password) {
      setError("Password tidak cocok.");
      return;
    }

    setLoading(true);

    try {
      const res = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/api/auth/register`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            username: form.username,
            display_name: form.display_name,
            password: form.password,
            email: form.email,
          }),
        },
      );

      const data = await res.json();

      if (res.ok && data.success) {
        router.push("/login");
      } else {
        setError(data.message || "Registrasi gagal.");
      }
    } catch (error) {
      setError("Terjadi kesalahan. Coba lagi.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex flex-col bg-background">
      <div className="flex justify-end p-4">
        <ThemeToggle />
      </div>

      <div className="flex-1 flex items-center justify-center px-4">
        <div className="w-full max-w-sm space-y-6">
          <div className="text-center space-y-1">
            <h1 className="text-3xl font-semibold tracking-tight">
              💸 Cateeet
            </h1>
            <p className="text-sm text-muted-foreground">
              Cateeet, biar gak lupa!
            </p>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Daftar</CardTitle>
              <CardDescription>
                Buat akun baru untuk mulai mencatat
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="display_name">Nama Lengkap</Label>
                  <Input
                    id="display_name"
                    name="display_name"
                    type="text"
                    placeholder="Ichwan Rizky"
                    value={form.display_name}
                    onChange={handleChange}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="username">
                    Username
                    <span className="ml-1 text-xs text-muted-foreground">
                      (auto-generated, bisa diubah)
                    </span>
                  </Label>
                  <Input
                    id="username"
                    name="username"
                    type="text"
                    placeholder="username"
                    value={form.username}
                    onChange={handleChange}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="email">Email</Label>
                  <Input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="ichwan@email.com"
                    value={form.email}
                    onChange={handleChange}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="password">Password</Label>
                  <Input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="••••••••"
                    value={form.password}
                    onChange={handleChange}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="confirm_password">Konfirmasi Password</Label>
                  <Input
                    id="confirm_password"
                    name="confirm_password"
                    type="password"
                    placeholder="••••••••"
                    value={form.confirm_password}
                    onChange={handleChange}
                    required
                  />
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
                <Button type="submit" className="w-full" disabled={loading}>
                  {loading ? "Mendaftar..." : "Daftar"}
                </Button>
                <p className="text-center text-sm text-muted-foreground">
                  Sudah punya akun?{" "}
                  <a
                    href="/login"
                    className="text-primary hover:underline font-medium"
                  >
                    Masuk
                  </a>
                </p>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>

      <div className="py-4 text-center text-xs text-muted-foreground">
        Developed by <span className="font-medium text-foreground">Waaang</span>{" "}
        · {new Date().getFullYear()}
      </div>
    </div>
  );
}
