import axios from "axios";
import { getSession } from "@/lib/auth";

const API_URL = process.env.API_URL || "http://localhost:8000";

// Client-side axios instance
export const api = axios.create({
  baseURL: `${API_URL}/api/v1`,
  headers: {
    "Content-Type": "application/json",
  },
});

// Server-side fetch dengan session token
export async function apiFetch<T>(
  endpoint: string,
  options?: RequestInit,
): Promise<T> {
  const session = await getSession();
  const token = session?.user?.token;

  const res = await fetch(`${API_URL}/api/v1${endpoint}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options?.headers,
    },
  });

  if (!res.ok) {
    throw new Error(`API error: ${res.status}`);
  }

  return res.json();
}
