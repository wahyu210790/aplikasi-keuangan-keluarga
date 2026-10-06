import React, { useEffect, useState } from 'react';
import api from '../services/api';
import Card from '../components/Card';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Input from '../components/Input';

export default function CurrencyConverter() {
  const [currencies, setCurrencies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Conversion Form
  const [amount, setAmount] = useState('100');
  const [fromCurrency, setFromCurrency] = useState('USD');
  const [toCurrency, setToCurrency] = useState('IDR');
  const [converting, setConverting] = useState(false);
  const [result, setResult] = useState(null);

  const fetchCurrencies = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get('/currencies');
      setCurrencies(res.data.currencies || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal memuat data mata uang');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCurrencies();
  }, []);

  const handleConvert = async (e) => {
    e?.preventDefault();
    if (!amount || Number(amount) <= 0) return;

    setConverting(true);
    setError(null);
    try {
      const res = await api.post('/currencies/convert', {
        amount: parseFloat(amount),
        from_currency: fromCurrency,
        to_currency: toCurrency,
      });
      setResult(res.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal melakukan konversi mata uang');
    } finally {
      setConverting(false);
    }
  };

  useEffect(() => {
    if (currencies.length > 0) {
      handleConvert();
    }
  }, [fromCurrency, toCurrency]);

  const formatNumber = (val) => {
    const num = Number(val);
    if (isNaN(num)) return '0';
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(num);
  };

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/4"></div>
          <div className="h-48 bg-gray-200 rounded"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Kalkulator Konversi Mata Uang</h1>
        <p className="text-gray-600 text-sm">Simulasi konversi dan estimasi nilai tukar valuta asing secara real-time</p>
      </div>

      {error && (
        <Alert type="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* Calculator Card */}
      <Card className="p-6 bg-white space-y-6">
        <form onSubmit={handleConvert} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <Input
              label="Jumlah Nominal"
              type="number"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Dari Mata Uang</label>
              <select
                value={fromCurrency}
                onChange={(e) => setFromCurrency(e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2"
              >
                {currencies.map((c) => (
                  <option key={c.code} value={c.code}>
                    {c.code} - {c.name} ({c.symbol})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Ke Mata Uang</label>
              <select
                value={toCurrency}
                onChange={(e) => setToCurrency(e.target.value)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2"
              >
                {currencies.map((c) => (
                  <option key={c.code} value={c.code}>
                    {c.code} - {c.name} ({c.symbol})
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <Button type="submit" disabled={converting} className="bg-indigo-600 hover:bg-indigo-700 text-white">
              {converting ? 'Mengonversi...' : 'Hitung Konversi'}
            </Button>
          </div>
        </form>

        {/* Result Display */}
        {result && (
          <div className="mt-6 p-6 bg-indigo-50 rounded-xl border border-indigo-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
              <span className="text-xs text-indigo-500 font-semibold uppercase tracking-wider">Hasil Konversi</span>
              <div className="text-3xl font-extrabold text-indigo-900 mt-1">
                {result.to_currency} {formatNumber(result.converted_amount)}
              </div>
              <p className="text-xs text-gray-600 mt-1">
                {result.original_amount} {result.from_currency} = {result.converted_amount} {result.to_currency}
              </p>
            </div>

            <div className="text-right border-t md:border-t-0 md:border-l border-indigo-200 pt-3 md:pt-0 md:pl-6">
              <span className="text-xs text-gray-500 block">Nilai Kurs Efektif</span>
              <span className="text-sm font-bold text-gray-800 font-mono">
                1 {result.from_currency} = {result.rate} {result.to_currency}
              </span>
            </div>
          </div>
        )}
      </Card>

      {/* Available Currencies List */}
      <Card className="p-6 bg-white space-y-4">
        <h2 className="text-lg font-bold text-gray-800 border-b pb-2">Daftar Kurs Baseline</h2>
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b bg-gray-50 text-xs font-semibold text-gray-600 uppercase">
                <th className="py-2 px-4">Kode</th>
                <th className="py-2 px-4">Nama Mata Uang</th>
                <th className="py-2 px-4">Simbol</th>
                <th className="py-2 px-4 text-center">Default</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {currencies.map((c) => (
                <tr key={c.code} className="hover:bg-gray-50">
                  <td className="py-2.5 px-4 font-mono font-bold text-indigo-600">{c.code}</td>
                  <td className="py-2.5 px-4 font-medium text-gray-900">{c.name}</td>
                  <td className="py-2.5 px-4 font-bold text-gray-700">{c.symbol}</td>
                  <td className="py-2.5 px-4 text-center">
                    {c.is_default ? (
                      <span className="bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded font-semibold">
                        Default System
                      </span>
                    ) : (
                      '-'
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
