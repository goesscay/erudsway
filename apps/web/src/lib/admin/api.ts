"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { paths } from "@erudsway/api-client";
import { api, API_URL, mutate, unwrap } from "@/lib/api/client";
import { meKey } from "@/lib/auth/session";

export type Range = { from: string; to: string };
export type StaffPermission =
  paths["/admin/users/{user}/access"]["put"]["requestBody"]["content"]["application/json"]["permissions"][number];
export type ReportName = "sales" | "courses" | "instructors" | "refunds";

export function useOverview(range: Range, enabled: boolean) {
  return useQuery({
    queryKey: ["admin", "overview", range],
    queryFn: async () => (await unwrap(api.GET("/admin/analytics/overview", { params: { query: range } }))).data,
    enabled,
    placeholderData: keepPreviousData,
  });
}

export function useQueues(enabled: boolean) {
  return useQuery({
    queryKey: ["admin", "queues"],
    queryFn: async () => (await unwrap(api.GET("/admin/queues"))).data,
    enabled,
    refetchInterval: 120_000,
  });
}

export function useReport(name: ReportName, range: Range, group: "day" | "month") {
  return useQuery({
    queryKey: ["admin", "report", name, range, group],
    queryFn: async () =>
      (await unwrap(api.GET("/admin/reports/{name}", { params: { path: { name }, query: { ...range, group } } }))).data,
    placeholderData: keepPreviousData,
  });
}

export function reportCsvUrl(name: ReportName, range: Range, group: "day" | "month") {
  return `${API_URL}/api/v1/admin/reports/${name}/csv?${new URLSearchParams({ ...range, group })}`;
}

async function fetchSettings() {
  return (await unwrap(api.GET("/admin/settings"))).data.items;
}
export type Setting = Awaited<ReturnType<typeof fetchSettings>>[number];

export function useSettings() {
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ["admin", "settings"], queryFn: fetchSettings });
  const save = useMutation({
    // `values` is a map of setting key to value; the generated type can't express that.
    mutationFn: (values: Record<string, string | number | boolean | null>) =>
      mutate(() => api.PUT("/admin/settings", { body: { values: values as unknown as string[] } })),
    onSuccess: (res) => queryClient.setQueryData(["admin", "settings"], res.data.items),
  });
  return { query, save };
}

export type AuditFilters = { action?: string; actor?: string; subject_type?: string; from?: string; to?: string };

export function useAuditLogs(filters: AuditFilters, page: number) {
  return useQuery({
    queryKey: ["admin", "audit", filters, page],
    queryFn: async () => unwrap(api.GET("/admin/audit-logs", { params: { query: { ...clean(filters), page } } })),
    placeholderData: keepPreviousData,
  });
}

export function useAuditActions() {
  return useQuery({
    queryKey: ["admin", "audit-actions"],
    queryFn: async () => (await unwrap(api.GET("/admin/audit-logs/actions"))).data.items,
  });
}

export function auditCsvUrl(filters: AuditFilters) {
  return `${API_URL}/api/v1/admin/audit-logs/csv?${new URLSearchParams(clean(filters))}`;
}

function clean(f: AuditFilters): Record<string, string> {
  return Object.fromEntries(Object.entries(f).filter(([, v]) => v !== undefined && v !== "")) as Record<string, string>;
}

export function useAdminUser(uuid: string) {
  return useQuery({
    queryKey: ["admin", "user", uuid],
    queryFn: async () => (await unwrap(api.GET("/admin/users/{user}", { params: { path: { user: uuid } } }))).data,
    retry: false,
  });
}
export type AdminUserDetail = NonNullable<ReturnType<typeof useAdminUser>["data"]>;

export function useUpdateAccess(uuid: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: { admin: boolean; super_admin?: boolean; roles?: string[]; permissions: StaffPermission[] }) =>
      mutate(() => api.PUT("/admin/users/{user}/access", { params: { path: { user: uuid } }, body })),
    onSuccess: async (res) => {
      queryClient.setQueryData(["admin", "user", uuid], res.data);
      await queryClient.invalidateQueries({ queryKey: rolesKey });
    },
  });
}

export function useUpdateUser(uuid: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: { name: string; email: string }) =>
      mutate(() => api.PATCH("/admin/users/{user}", { params: { path: { user: uuid } }, body })),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["admin", "user", uuid] });
      await queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
}

export function useCreateUser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: { name: string; email: string; roles: string[] }) =>
      mutate(() => api.POST("/admin/users", { body: { ...body, ...(body.roles.length ? {} : { roles: undefined }) } })),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      await queryClient.invalidateQueries({ queryKey: rolesKey });
    },
  });
}

/** Email the "set your password" link again. */
export async function resendInvite(uuid: string) {
  await mutate(() => api.POST("/admin/users/{user}/invite", { params: { path: { user: uuid } } }));
}

const rolesKey = ["admin", "roles"];

export function useRoles() {
  return useQuery({
    queryKey: [...rolesKey, "list"],
    queryFn: async () => (await unwrap(api.GET("/admin/roles"))).data.items,
  });
}
export type AdminRole = NonNullable<ReturnType<typeof useRoles>["data"]>[number];

export function useSaveRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      ...body
    }: {
      id?: number;
      name: string;
      description: string | null;
      permissions: StaffPermission[];
    }) =>
      id === undefined
        ? mutate(() => api.POST("/admin/roles", { body }))
        : mutate(() => api.PUT("/admin/roles/{role}", { params: { path: { role: id } }, body })),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: rolesKey }),
  });
}

export function useDeleteRole() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => mutate(() => api.DELETE("/admin/roles/{role}", { params: { path: { role: id } } })),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesKey });
      await queryClient.invalidateQueries({ queryKey: ["admin", "user"] });
    },
  });
}

export function usePermissions() {
  return useQuery({
    queryKey: [...rolesKey, "permissions"],
    queryFn: async () => (await unwrap(api.GET("/admin/permissions"))).data.items,
  });
}
export type PermissionInfo = NonNullable<ReturnType<typeof usePermissions>["data"]>[number];

export function usePermissionHolders(key: StaffPermission | null) {
  return useQuery({
    queryKey: [...rolesKey, "holders", key],
    queryFn: async () =>
      (await unwrap(api.GET("/admin/permissions/{permission}/users", { params: { path: { permission: key! } } }))).data
        .items,
    enabled: key !== null,
  });
}

/** Display names for the built-in roles; custom roles show as named. */
export function roleLabel(name: string) {
  return (
    (
      { student: "Learner", instructor: "Instructor", admin: "Administrator", super_admin: "Super admin" } as Record<
        string,
        string
      >
    )[name] ?? name
  );
}

/** Start "view as". The whole app re-reads who is signed in. */
export async function impersonate(uuid: string) {
  await mutate(() => api.POST("/admin/users/{user}/impersonate", { params: { path: { user: uuid } } }));
}

export async function stopImpersonating() {
  await mutate(() => api.POST("/auth/impersonation/stop"));
}

export { meKey };
