import { useHttp } from '@inertiajs/react';
import {
    CheckCircle2,
    ImagePlus,
    LoaderCircle,
    UploadCloud,
    X,
} from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import type { FileRejection } from 'react-dropzone';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { store } from '@/routes/products/images';

type GalleryImage = { id: number; url: string };
type QueuedImage = {
    id: string;
    file: File;
    preview: string;
    status: 'pending' | 'uploading' | 'uploaded' | 'error';
    progress: number;
    error?: string;
};
type Props = {
    productId: number;
    teamSlug: string;
    remainingSlots: number;
    onUploaded: (image: GalleryImage) => void;
    onUploadingChange: (uploading: boolean) => void;
};

function rejectionMessage(rejection: FileRejection): string {
    const code = rejection.errors[0]?.code;
    if (code === 'file-too-large')
        return `${rejection.file.name}: supera el límite de 1 MB.`;
    if (code === 'file-invalid-type')
        return `${rejection.file.name}: usa JPG, PNG o WebP.`;
    if (code === 'too-many-files')
        return 'La selección supera los espacios disponibles en la galería.';
    return `${rejection.file.name}: no se pudo seleccionar el archivo.`;
}

export default function ProductImageUploader({
    productId,
    teamSlug,
    remainingSlots,
    onUploaded,
    onUploadingChange,
}: Props) {
    const upload = useHttp<{ image: File | null }, { image: GalleryImage }>({
        image: null,
    });
    const { cancel } = upload;
    const [queue, setQueue] = useState<QueuedImage[]>([]);
    const [selectionErrors, setSelectionErrors] = useState<string[]>([]);
    const [uploading, setUploading] = useState(false);
    const busy = useRef(false);
    const mounted = useRef(true);
    const previews = useRef(new Set<string>());
    const nextId = useRef(0);
    const helpId = useId();
    const pending = queue.filter((image) => image.status !== 'uploaded');
    const available = Math.max(0, remainingSlots - pending.length);

    useEffect(() => {
        mounted.current = true;
        const objectUrls = previews.current;
        return () => {
            mounted.current = false;
            cancel();
            objectUrls.forEach((url) => URL.revokeObjectURL(url));
            objectUrls.clear();
        };
    }, [cancel]);

    const { getRootProps, getInputProps, isDragActive, isDragReject } =
        useDropzone({
            accept: {
                'image/jpeg': ['.jpg', '.jpeg'],
                'image/png': ['.png'],
                'image/webp': ['.webp'],
            },
            multiple: true,
            maxSize: 1024 * 1024,
            maxFiles: Math.max(1, available),
            disabled: uploading || available === 0,
            onDrop(acceptedFiles, rejections) {
                const errors = [...new Set(rejections.map(rejectionMessage))];
                const selected: QueuedImage[] = [];
                for (const file of acceptedFiles) {
                    if (
                        queue.some(
                            (image) =>
                                image.file.name === file.name &&
                                image.file.size === file.size &&
                                image.file.lastModified === file.lastModified,
                        )
                    ) {
                        errors.push(`${file.name}: ya está en la lista.`);
                        continue;
                    }
                    const preview = URL.createObjectURL(file);
                    previews.current.add(preview);
                    selected.push({
                        id: String(++nextId.current),
                        file,
                        preview,
                        status: 'pending',
                        progress: 0,
                    });
                }
                setSelectionErrors(errors);
                setQueue((current) => [...current, ...selected]);
            },
        });

    function updateImage(id: string, changes: Partial<QueuedImage>) {
        if (mounted.current)
            setQueue((current) =>
                current.map((image) =>
                    image.id === id ? { ...image, ...changes } : image,
                ),
            );
    }

    function removeImage(image: QueuedImage) {
        URL.revokeObjectURL(image.preview);
        previews.current.delete(image.preview);
        setQueue((current) => current.filter((item) => item.id !== image.id));
        setSelectionErrors([]);
    }

    async function uploadImages() {
        if (busy.current || pending.length === 0) return;
        busy.current = true;
        setUploading(true);
        onUploadingChange(true);
        setSelectionErrors([]);
        try {
            for (const image of pending) {
                if (!mounted.current) break;
                updateImage(image.id, {
                    status: 'uploading',
                    progress: 0,
                    error: undefined,
                });
                upload.clearErrors();
                upload.transform(() => ({ image: image.file }));
                let message = 'No se pudo subir la imagen. Intenta de nuevo.';
                try {
                    const result = await upload.submit(
                        store({ current_team: teamSlug, product: productId }),
                        {
                            onProgress: (progress) =>
                                updateImage(image.id, {
                                    progress:
                                        progress.percentage ??
                                        (progress.total
                                            ? Math.round(
                                                  (progress.loaded /
                                                      progress.total) *
                                                      100,
                                              )
                                            : 0),
                                }),
                            onError: (errors) => {
                                message =
                                    errors.image ||
                                    'La imagen no cumple los requisitos.';
                            },
                            onHttpException: (response) => {
                                message =
                                    response.status === 419
                                        ? 'La sesión expiró. Recarga la página para continuar.'
                                        : response.status === 403
                                          ? 'No tienes permiso para subir imágenes.'
                                          : 'No se pudo guardar la imagen. Intenta de nuevo.';
                            },
                            onNetworkError: () => {
                                message =
                                    'No se pudo conectar. Revisa tu conexión e intenta de nuevo.';
                            },
                        },
                    );
                    if (!mounted.current) break;
                    if (result?.image) {
                        onUploaded(result.image);
                        updateImage(image.id, {
                            status: 'uploaded',
                            progress: 100,
                        });
                    } else {
                        updateImage(image.id, {
                            status: 'error',
                            error: message,
                        });
                    }
                } catch {
                    updateImage(image.id, { status: 'error', error: message });
                }
            }
        } finally {
            busy.current = false;
            if (mounted.current) {
                setUploading(false);
                onUploadingChange(false);
            }
        }
    }

    return (
        <div className="flex flex-col gap-4 rounded-xl border p-4">
            <div
                {...getRootProps({
                    role: 'button',
                    'aria-label': 'Seleccionar o arrastrar imágenes',
                    'aria-describedby': helpId,
                    'aria-disabled': uploading || available === 0,
                    className: cn(
                        'flex min-h-40 flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed p-6 text-center transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        uploading || available === 0
                            ? 'cursor-not-allowed opacity-60'
                            : 'cursor-pointer hover:border-primary hover:bg-muted/50',
                        isDragActive && 'border-primary bg-muted',
                        isDragReject && 'border-destructive',
                    ),
                })}
            >
                <input
                    {...getInputProps({ 'aria-label': 'Seleccionar imágenes' })}
                />
                <UploadCloud className="size-8 text-muted-foreground" />
                <p className="font-medium">
                    {uploading
                        ? 'Subiendo imágenes…'
                        : available === 0
                          ? 'No quedan espacios disponibles'
                          : isDragActive
                            ? 'Suelta las imágenes aquí'
                            : 'Arrastra imágenes aquí o haz clic para seleccionarlas'}
                </p>
                <p className="text-sm text-muted-foreground">
                    Puedes seleccionar varios archivos a la vez.
                </p>
            </div>
            <p id={helpId} className="text-sm text-muted-foreground">
                JPG, PNG o WebP. Hasta 1 MB y 4096 × 4096 píxeles por imagen.{' '}
                {available} espacios disponibles.
            </p>
            {selectionErrors.length > 0 && (
                <ul role="alert" className="space-y-1 text-sm text-destructive">
                    {selectionErrors.map((error) => (
                        <li key={error}>{error}</li>
                    ))}
                </ul>
            )}
            {queue.length > 0 && (
                <ul className="grid gap-3 sm:grid-cols-2">
                    {queue.map((image) => (
                        <li
                            key={image.id}
                            className="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <img
                                src={image.preview}
                                alt={`Vista previa de ${image.file.name}`}
                                className="size-16 shrink-0 rounded-md bg-muted object-contain"
                            />
                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                <p
                                    className="truncate text-sm font-medium"
                                    title={image.file.name}
                                >
                                    {image.file.name}
                                </p>
                                <div className="text-xs text-muted-foreground">
                                    {(image.file.size / 1024).toFixed(0)} KB
                                </div>
                                {image.status === 'uploaded' ? (
                                    <Badge
                                        variant="secondary"
                                        className="self-start"
                                    >
                                        <CheckCircle2 className="size-3" />
                                        Subida
                                    </Badge>
                                ) : image.status === 'uploading' ? (
                                    <>
                                        <span className="flex items-center gap-1 text-xs">
                                            <LoaderCircle className="size-3 animate-spin" />
                                            {image.progress < 100
                                                ? `${image.progress}%`
                                                : 'Guardando…'}
                                        </span>
                                        <progress
                                            value={image.progress}
                                            max={100}
                                            aria-label={`Progreso de ${image.file.name}`}
                                            className="w-full"
                                        />
                                    </>
                                ) : image.status === 'error' ? (
                                    <p
                                        role="alert"
                                        className="text-xs text-destructive"
                                    >
                                        {image.error}
                                    </p>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        Lista para subir
                                    </span>
                                )}
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={uploading}
                                onClick={() => removeImage(image)}
                                aria-label={`Quitar ${image.file.name} de la lista`}
                            >
                                <X />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <p aria-live="polite" className="text-sm text-muted-foreground">
                {queue.filter((image) => image.status === 'uploaded').length >
                    0 &&
                    `${queue.filter((image) => image.status === 'uploaded').length} imágenes subidas. `}
                {queue.filter((image) => image.status === 'error').length > 0 &&
                    `${queue.filter((image) => image.status === 'error').length} imágenes con errores.`}
            </p>
            {pending.length > 0 && (
                <Button
                    type="button"
                    disabled={uploading}
                    onClick={() => {
                        void uploadImages();
                    }}
                    className="self-start"
                >
                    <ImagePlus />
                    {uploading
                        ? 'Subiendo…'
                        : `Subir ${pending.length} ${pending.length === 1 ? 'imagen' : 'imágenes'}`}
                </Button>
            )}
        </div>
    );
}
