/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect } from 'react';
import { 
  BarChart3, 
  TrendingUp, 
  Users, 
  DollarSign, 
  ArrowUpRight, 
  ArrowDownRight,
  Plus,
  Search,
  LayoutDashboard,
  PieChart as PieChartIcon,
  Settings,
  Sparkles,
  Calendar,
  Filter,
  Download,
  ChevronRight,
  Clock,
  PhoneIncoming,
  PhoneMissed,
  PhoneOff
} from 'lucide-react';
import { 
  LineChart, 
  Line, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer,
  AreaChart,
  Area,
  BarChart,
  Bar,
  Cell,
  PieChart,
  Pie
} from 'recharts';
import { motion, AnimatePresence } from 'motion/react';
import { cn } from './lib/utils';

// Types pour nos stats
interface Stats {
  total_calls: number;
  answered: number;
  missed: number;
  abandoned: number;
  avg_duration: number;
  avg_wait: number;
}

export default function App() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [aiAnalysis, setAiAnalysis] = useState<string | null>(null);
  const [analyzing, setAnalyzing] = useState(false);
  const [dateRange, setDateRange] = useState({ from: '', to: '' });

  // Simulation de données pour le graphique (à remplacer par API)
  const chartData = [
    { name: '09h', calls: 45, answered: 40 },
    { name: '10h', calls: 52, answered: 48 },
    { name: '11h', calls: 61, answered: 55 },
    { name: '12h', calls: 38, answered: 35 },
    { name: '13h', calls: 42, answered: 39 },
    { name: '14h', calls: 58, answered: 52 },
    { name: '15h', calls: 65, answered: 60 },
    { name: '16h', calls: 48, answered: 44 },
  ];

  useEffect(() => {
    fetchStats();
  }, []);

  const fetchStats = async () => {
    setLoading(true);
    try {
      // Appel vers l'API PHP
      const res = await fetch('/api/index.php?action=overview');
      const data = await res.json();
      setStats(data);
    } catch (e) {
      console.error("Erreur Fetch PHP:", e);
      setStats({
        total_calls: 0,
        answered: 0,
        missed: 0,
        abandoned: 0,
        avg_duration: 0,
        avg_wait: 0
      });
    } finally {
      setLoading(false);
    }
  };

  const runAiAnalysis = async () => {
    if (!stats) return;
    setAnalyzing(true);
    try {
      const res = await fetch('/api/index.php?action=ai_analyze', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stats })
      });
      const data = await res.json();
      setAiAnalysis(data.analysis);
    } catch (e) {
      console.error(e);
    } finally {
      setAnalyzing(false);
    }
  };

  return (
    <div className="flex h-screen bg-[#0a0a0b] text-[#e1e1e3] font-mono selection:bg-blue-500/30">
      {/* Sidebar - Look Technique */}
      <aside className="w-64 border-r border-white/5 bg-[#0d0d0f] p-6 flex flex-col gap-8">
        <div className="flex items-center gap-3 px-2">
          <div className="w-8 h-8 bg-blue-600 rounded-sm flex items-center justify-center shadow-[0_0_15px_rgba(37,99,235,0.4)]">
            <BarChart3 size={18} className="text-white" />
          </div>
          <span className="font-bold tracking-tighter text-lg">VITA_CORE v3</span>
        </div>

        <nav className="flex flex-col gap-1">
          <NavItem icon={LayoutDashboard} label="DASHBOARD" active />
          <NavItem icon={Users} label="AGENTS" />
          <NavItem icon={PieChartIcon} label="METEO" />
          <NavItem icon={Download} label="IMPORTS" />
          <NavItem icon={Settings} label="CONFIG" />
        </nav>

        <div className="mt-auto p-4 bg-white/5 rounded-lg border border-white/5">
          <div className="flex items-center gap-2 text-[10px] text-blue-400 mb-2">
            <div className="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
            LIVE_SYNC ACTIVE
          </div>
          <p className="text-[10px] text-white/40 leading-tight">
            Base de données unifiée : v_stats_all connectée.
          </p>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-y-auto p-8">
        <header className="flex items-center justify-between mb-12">
          <div>
            <h1 className="text-2xl font-bold tracking-tight mb-1">OPERATIONAL_OVERVIEW</h1>
            <div className="flex items-center gap-4 text-[11px] text-white/40">
              <span className="flex items-center gap-1"><Calendar size={12} /> 24 MAR 2026</span>
              <span className="flex items-center gap-1"><Clock size={12} /> LAST_SYNC: 2m ago</span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button 
              onClick={runAiAnalysis}
              disabled={analyzing}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-bold rounded-sm transition-all shadow-lg shadow-blue-900/20"
            >
              {analyzing ? <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Sparkles size={14} />}
              ANALYSE_IA
            </button>
            <div className="h-8 w-px bg-white/10 mx-2" />
            <button className="p-2 hover:bg-white/5 rounded-sm transition-colors text-white/60">
              <Filter size={18} />
            </button>
          </div>
        </header>

        {/* AI Insights Panel */}
        <AnimatePresence>
          {aiAnalysis && (
            <motion.div 
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              className="mb-8 overflow-hidden"
            >
              <div className="p-6 bg-blue-600/10 border border-blue-500/20 rounded-sm relative">
                <button 
                  onClick={() => setAiAnalysis(null)}
                  className="absolute top-4 right-4 text-blue-400 hover:text-blue-300"
                >
                  <Plus size={16} className="rotate-45" />
                </button>
                <div className="flex items-start gap-4">
                  <div className="p-2 bg-blue-600 rounded-sm">
                    <Sparkles size={16} className="text-white" />
                  </div>
                  <div>
                    <h3 className="text-xs font-bold text-blue-400 mb-2 tracking-widest">GEMINI_INSIGHTS</h3>
                    <p className="text-sm leading-relaxed text-blue-100/80 italic">
                      {aiAnalysis}
                    </p>
                  </div>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* KPI Grid - Look "Hardware" */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <KpiCard 
            label="TOTAL_CALLS" 
            value={stats?.total_calls || 0} 
            icon={PhoneIncoming}
            trend="+12%"
          />
          <KpiCard 
            label="ANSWERED" 
            value={stats?.answered || 0} 
            icon={PhoneIncoming}
            color="text-emerald-400"
            trend="+5%"
          />
          <KpiCard 
            label="MISSED" 
            value={stats?.missed || 0} 
            icon={PhoneMissed}
            color="text-rose-400"
            trend="-2%"
          />
          <KpiCard 
            label="ABANDONED" 
            value={stats?.abandoned || 0} 
            icon={PhoneOff}
            color="text-amber-400"
            trend="+1%"
          />
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 p-6 bg-[#0d0d0f] border border-white/5 rounded-sm">
            <div className="flex items-center justify-between mb-8">
              <h3 className="text-xs font-bold tracking-widest text-white/40">HOURLY_DISTRIBUTION</h3>
              <div className="flex gap-4 text-[10px]">
                <span className="flex items-center gap-1.5"><div className="w-2 h-2 bg-blue-600" /> TOTAL</span>
                <span className="flex items-center gap-1.5"><div className="w-2 h-2 bg-emerald-500" /> ANSWERED</span>
              </div>
            </div>
            <div className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData}>
                  <defs>
                    <linearGradient id="colorCalls" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#2563eb" stopOpacity={0.3}/>
                      <stop offset="95%" stopColor="#2563eb" stopOpacity={0}/>
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#ffffff05" />
                  <XAxis 
                    dataKey="name" 
                    axisLine={false} 
                    tickLine={false} 
                    tick={{ fontSize: 10, fill: '#ffffff40' }}
                  />
                  <YAxis 
                    axisLine={false} 
                    tickLine={false} 
                    tick={{ fontSize: 10, fill: '#ffffff40' }}
                  />
                  <Tooltip 
                    contentStyle={{ 
                      backgroundColor: '#0d0d0f', 
                      border: '1px solid rgba(255,255,255,0.1)',
                      fontSize: '10px',
                      borderRadius: '2px'
                    }}
                  />
                  <Area 
                    type="monotone" 
                    dataKey="calls" 
                    stroke="#2563eb" 
                    strokeWidth={2}
                    fillOpacity={1} 
                    fill="url(#colorCalls)" 
                  />
                  <Area 
                    type="monotone" 
                    dataKey="answered" 
                    stroke="#10b981" 
                    strokeWidth={2}
                    fill="transparent"
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="p-6 bg-[#0d0d0f] border border-white/5 rounded-sm">
            <h3 className="text-xs font-bold tracking-widest text-white/40 mb-8">TEAM_PERFORMANCE</h3>
            <div className="flex flex-col gap-6">
              <TeamRow label="DEB" value={84} color="bg-blue-500" />
              <TeamRow label="CDN" value={72} color="bg-emerald-500" />
              <TeamRow label="N2" value={91} color="bg-amber-500" />
              <TeamRow label="SQ" value={65} color="bg-rose-500" />
            </div>
            
            <div className="mt-12 pt-8 border-t border-white/5">
              <div className="flex items-center justify-between text-[11px] text-white/40 mb-4">
                <span>AVG_WAIT_TIME</span>
                <span className="text-white font-bold">24s</span>
              </div>
              <div className="flex items-center justify-between text-[11px] text-white/40">
                <span>AVG_DURATION</span>
                <span className="text-white font-bold">4m 12s</span>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}

function NavItem({ icon: Icon, label, active = false }: { icon: any, label: string, active?: boolean }) {
  return (
    <button className={cn(
      "flex items-center gap-3 px-3 py-2.5 rounded-sm text-[11px] font-bold tracking-widest transition-all group",
      active ? "bg-blue-600/10 text-blue-400 border-l-2 border-blue-600" : "text-white/40 hover:text-white hover:bg-white/5"
    )}>
      <Icon size={14} className={cn("transition-colors", active ? "text-blue-400" : "text-white/20 group-hover:text-white/60")} />
      {label}
    </button>
  );
}

function KpiCard({ label, value, icon: Icon, color = "text-white", trend }: { label: string, value: number | string, icon: any, color?: string, trend?: string }) {
  return (
    <div className="p-5 bg-[#0d0d0f] border border-white/5 rounded-sm group hover:border-white/10 transition-colors">
      <div className="flex items-center justify-between mb-4">
        <div className="p-2 bg-white/5 rounded-sm">
          <Icon size={14} className="text-white/40" />
        </div>
        {trend && (
          <span className={cn(
            "text-[10px] font-bold",
            trend.startsWith('+') ? "text-emerald-500" : "text-rose-500"
          )}>
            {trend}
          </span>
        )}
      </div>
      <div className="text-[10px] text-white/30 font-bold tracking-widest mb-1">{label}</div>
      <div className={cn("text-2xl font-bold tracking-tighter", color)}>{value}</div>
    </div>
  );
}

function TeamRow({ label, value, color }: { label: string, value: number, color: string }) {
  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between text-[10px] font-bold tracking-widest">
        <span className="text-white/60">{label}</span>
        <span>{value}%</span>
      </div>
      <div className="h-1 bg-white/5 rounded-full overflow-hidden">
        <motion.div 
          initial={{ width: 0 }}
          animate={{ width: `${value}%` }}
          className={cn("h-full rounded-full", color)}
        />
      </div>
    </div>
  );
}
