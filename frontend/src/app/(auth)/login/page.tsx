"use client";

import { useState } from "react";
import { signIn } from "next-auth/react";
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

export default function LoginPage() {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError("");

    const res = await signIn("credentials", {
      username,
      password,
      redirect: false,
    });

    setLoading(false);

    if (res?.error) {
      setError("Username atau password salah.");
    } else {
      router.push("/transactions");
    }
  };

  return (
    <div className="min-h-screen flex flex-col bg-background">
      {/* Theme toggle pojok kanan atas */}
      <div className="flex justify-end p-4">
        <ThemeToggle />
      </div>

      {/* Center content */}
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
              <CardTitle className="text-lg">Masuk</CardTitle>
              <CardDescription>
                Masukkan username dan password kamu
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="username">Username</Label>
                  <Input
                    id="username"
                    type="text"
                    placeholder="username"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="password">Password</Label>
                  <Input
                    id="password"
                    type="password"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                  />
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
                <Button type="submit" className="w-full" disabled={loading}>
                  {loading ? "Masuk..." : "Masuk"}
                </Button>
                <p className="text-center text-sm text-muted-foreground">
                  Belum punya akun?{" "}
                  <a
                    href="/register"
                    className="text-primary hover:underline font-medium"
                  >
                    Daftar sekarang
                  </a>
                </p>
                <p className="text-center text-sm text-muted-foreground">
                  <a
                    href="/tutorial_public"
                    className="text-primary hover:underline font-medium"
                  >
                    Lihat cara penggunaan bot
                  </a>
                </p>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Footer */}
      <div className="py-4 text-center text-xs text-muted-foreground">
        Developed by <span className="font-medium text-foreground">Waaang</span>{" "}
        · {new Date().getFullYear()}
      </div>
    </div>
  );
}
