import AppLayout from "@/layouts/app-layout"

import { Button } from "@/components/ui/button"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"

import { router } from "@inertiajs/react"
import { useEffect, useState } from "react"
import { Input } from "@/components/ui/input"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { route } from "ziggy-js"

import { Badge } from "@/components/ui/badge"

type Plazo = {
    situacion: "sin_fecha" | "aun_no_abre" | "en_plazo" | "por_vencer" | "fuera_de_plazo" | "tomada" | "recibido"
    fecha_apertura: string | null
    fecha_limite: string | null
    dias_restantes: number | null
    porcentaje_transcurrido: number | null
    texto: string
}

type Resultado = {
    no_cia: string
    numero_base: string | null
    identificacion: string
    nombre: string
    serologia: "S" | null
    fecha_toma: string | null
    fecha_resultado: string | null
    asesor_dni: string | null
    asesor_nombre: string | null
    fecha_nacimiento: string | null
    plazo: Plazo | null
}

type Asesor = {
    dni: string
    nombre: string | null
}

type ResultadosPaginados = {
    data: Resultado[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

type EstadoSerologia = "por_vencer" | "pendiente" | "tomada" | "recibido"

const opcionesEstado: EstadoSerologia[] = ["por_vencer", "pendiente", "tomada", "recibido"]

const estados = {
    por_vencer: {
        texto: "Por vencer",
        clase: "bg-red-50 text-red-700 border-red-700",
        descripcion: "7 días o menos",
        borde: "border-l-red-500",
        seleccion: "ring-red-600 bg-red-50 dark:bg-red-950/30",
    },
    recibido: {
        texto: "Resultado recibido",
        clase: "bg-green-50 text-green-700 border-green-700",
        descripcion: "Proceso completo",
        borde: "border-l-green-600",
        seleccion: "ring-green-600 bg-green-50 dark:bg-green-950/30",
    },
    tomada: {
        texto: "Tomada",
        clase: "bg-blue-50 text-blue-700 border-blue-700",
        descripcion: "Esperando resultado",
        borde: "border-l-blue-500",
        seleccion: "ring-blue-600 bg-blue-50 dark:bg-blue-950/30",
    },
    pendiente: {
        texto: "Pendiente",
        clase: "bg-yellow-50 text-yellow-700 border-yellow-700",
        descripcion: "Aún sin toma registrada",
        borde: "border-l-yellow-500",
        seleccion: "ring-yellow-600 bg-yellow-50 dark:bg-yellow-950/30",
    },
}

const avisosPlazo = {
    sin_fecha: null,
    en_plazo: null,
    tomada: null,
    recibido: null,
    aun_no_abre: {
        texto: "Aún no abre",
        clase: "bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-200",
    },
    por_vencer: {
        texto: "Por vencer",
        clase: "bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-950 dark:text-orange-200",
    },
    fuera_de_plazo: {
        texto: "Fuera de plazo",
        clase: "bg-red-100 text-red-800 border-red-200 dark:bg-red-950 dark:text-red-200",
    },
}

function PlazoCliente({ plazo }: { plazo: Plazo | null }) {
    if (plazo === null) return <span className="text-muted-foreground">—</span>

    return (
        <div className="min-w-44 space-y-2 py-1">
            {plazo.porcentaje_transcurrido !== null && (
                <div
                    role="progressbar"
                    aria-label="Plazo para la toma de muestra"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={plazo.porcentaje_transcurrido}
                    aria-valuetext={plazo.texto}
                    className="relative h-1.5 w-44 rounded-full bg-indigo-200 dark:bg-indigo-900"
                >
                    <div className="absolute inset-x-0.5 top-1/2">
                        <span
                            aria-hidden="true"
                            className="absolute h-4 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-slate-800 dark:bg-slate-100"
                            style={{ left: `${plazo.porcentaje_transcurrido}%` }}
                        />
                    </div>
                </div>
            )}
            <p className="text-xs text-muted-foreground">{plazo.texto}</p>
        </div>
    )
}

export default function Serologia({
    resultados,
    buscar,
    estado,
    conteos,
    asesor,
    asesores,
}: {
    resultados: ResultadosPaginados
    buscar: string
    estado: EstadoSerologia | null
    conteos: Record<EstadoSerologia, number>
    asesor: string | null
    asesores: Asesor[]
}) {

    const [texto, setTexto] = useState(buscar)
    const [estadoSeleccionado, setEstadoSeleccionado] = useState(estado)
    const [asesorSeleccionado, setAsesorSeleccionado] = useState(asesor)
    const [cargando, setCargando] = useState(false)

    const opcionesAsesor = [
        { value: "__todos__", label: "Todos los asesores" },
        ...asesores.map((opcion) => ({ value: opcion.dni, label: opcion.nombre ?? opcion.dni })),
    ]

    const filtrosPendientes = texto.trim() !== buscar
        || estadoSeleccionado !== estado
        || asesorSeleccionado !== asesor
    const actualizando = filtrosPendientes || cargando

    useEffect(() => {
        if (texto.trim() === buscar && estadoSeleccionado === estado && asesorSeleccionado === asesor) return

        let cancelarPeticion: (() => void) | undefined

        const temporizador = setTimeout(() => {
            router.get(
                route("serologia"),
                {
                    buscar: texto.trim(),
                    ...(estadoSeleccionado ? { estado: estadoSeleccionado } : {}),
                    ...(asesorSeleccionado ? { asesor: asesorSeleccionado } : {}),
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setCargando(true),
                    onFinish: () => setCargando(false),
                    onCancelToken: (token) => {
                        cancelarPeticion = () => token.cancel()
                    },
                }
            )
        }, estadoSeleccionado !== estado || asesorSeleccionado !== asesor ? 0 : 350)

        return () => {
            clearTimeout(temporizador)
            cancelarPeticion?.()
        }
    }, [texto, buscar, estadoSeleccionado, estado, asesorSeleccionado, asesor])

    return (
        <AppLayout title="Operaciones - Serología">
            <h1 className="text-2xl font-semibold">Serología de clientes</h1>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" role="group" aria-label="Filtrar por estado">
                {opcionesEstado.map((opcion) => {
                    const configuracion = estados[opcion]
                    const seleccionado = estadoSeleccionado === opcion

                    return (
                        <button
                            key={opcion}
                            type="button"
                            aria-pressed={seleccionado}
                            aria-label={`${configuracion.texto}: ${conteos[opcion]} registros`}
                            onClick={() => setEstadoSeleccionado((actual) => actual === opcion ? null : opcion)}
                            className={`flex cursor-pointer flex-col gap-1 rounded-xl border border-l-4 bg-card p-4 text-left text-card-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${configuracion.borde} ${seleccionado ? `ring-2 ${configuracion.seleccion}` : ""}`}
                        >
                            <span className="text-3xl font-bold tabular-nums">{conteos[opcion]}</span>
                            <span className="text-sm font-semibold">{configuracion.texto}</span>
                            <span className="text-xs text-muted-foreground">{configuracion.descripcion}</span>
                        </button>
                    )
                })}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <Input
                    aria-label="Buscar clientes"
                    placeholder="Buscar por nombre, DNI o código"
                    value={texto}
                    onChange={(event) => setTexto(event.target.value)}
                    className="max-w-md"
                    maxLength={100}
                />

                <Select
                    items={opcionesAsesor}
                    value={asesorSeleccionado ?? "__todos__"}
                    onValueChange={(valor) => setAsesorSeleccionado(valor === "__todos__" ? null : valor)}
                >
                    <SelectTrigger aria-label="Filtrar por asesor" className="w-full sm:w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {opcionesAsesor.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                {opcion.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {estadoSeleccionado && (
                    <Button variant="outline" onClick={() => setEstadoSeleccionado(null)}>
                        Ver todos
                    </Button>
                )}
            </div>

            <p role="status" className="min-h-5 text-sm text-muted-foreground">
                {actualizando ? "Actualizando resultados..." : ""}
            </p>

            <Table aria-busy={actualizando}>
                <TableHeader>
                    <TableRow>
                        <TableHead>Cliente</TableHead>
                        <TableHead>Asesor</TableHead>
                        <TableHead>Plazo</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {resultados.data.map((fila) => {

                        const estado = fila.serologia === "S"
                                        ? estados.recibido
                                        : fila.fecha_toma !== null
                                            ? estados.tomada
                                            : estados.pendiente
                        const avisoPlazo = fila.plazo ? avisosPlazo[fila.plazo.situacion] : null

                        return (
                            <TableRow
                                key={JSON.stringify([
                                    fila.no_cia,
                                    fila.numero_base,
                                    fila.identificacion,
                                    fila.nombre,
                                    fila.serologia,
                                    fila.fecha_toma,
                                    fila.fecha_resultado,
                                    fila.asesor_dni,
                                    fila.asesor_nombre,
                                    fila.fecha_nacimiento,
                                ])}
                            >
                                <TableCell>
                                    <p className="font-bold">{fila.nombre}</p>
                                    <p className="font-light flex gap-2 text-xs text-gray-500">
                                        <span>{fila.identificacion}</span>
                                        <span>·</span>
                                        <span>{fila.numero_base ?? '-'}</span>
                                    </p>
                                </TableCell>
                                <TableCell>{fila.asesor_nombre ?? fila.asesor_dni ?? "Sin asesor"}</TableCell>
                                <TableCell><PlazoCliente plazo={fila.plazo} /></TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge className={`${estado.clase} py-3 px-3`}>
                                            {estado.texto}
                                        </Badge>
                                        {avisoPlazo && (
                                            <Badge className={`${avisoPlazo.clase} rounded-md px-2 py-0.5 text-xs`}>
                                                {avisoPlazo.texto}
                                            </Badge>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right">
                                    {fila.serologia === null && fila.fecha_toma === null && (
                                        <Button variant="outline" className="cursor-pointer">Enviar recordatorio</Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        )
                    })}

                    {resultados.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={5} className="py-6 text-center">
                                No se encontraron resultados.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            <div className="flex flex-wrap items-center justify-between gap-4">
                <p className="text-sm text-muted-foreground">
                    {resultados.total} resultados · Página {resultados.current_page}
                    {" de "}
                    {resultados.last_page}
                </p>

                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        disabled={actualizando || !resultados.prev_page_url}
                        className="cursor-pointer"
                        onClick={() => {
                            if (resultados.prev_page_url) {
                                router.get(resultados.prev_page_url, {}, {
                                    preserveState: true,
                                    preserveScroll: true,
                                    onStart: () => setCargando(true),
                                    onFinish: () => setCargando(false),
                                })
                            }
                        }}
                    >
                        Anterior
                    </Button>

                    <Button
                        variant="outline"
                        disabled={actualizando || !resultados.next_page_url}
                        className="cursor-pointer"
                        onClick={() => {
                            if (resultados.next_page_url) {
                                router.get(resultados.next_page_url, {}, {
                                    preserveState: true,
                                    preserveScroll: true,
                                    onStart: () => setCargando(true),
                                    onFinish: () => setCargando(false),
                                })
                            }
                        }}
                    >
                        Siguiente
                    </Button>
                </div>
            </div>
        </AppLayout >
    )
}
