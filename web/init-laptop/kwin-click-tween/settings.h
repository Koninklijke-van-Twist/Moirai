// SPDX-License-Identifier: GPL-2.0-or-later
#pragma once
#include <QEasingCurve>
#include <KConfigGroup>
#include <QString>
#include <algorithm>

namespace ClickTween {
struct Curve { const char *name; QEasingCurve::Type type; };
inline constexpr Curve curves[] = {
    {"Linear", QEasingCurve::Linear},
#define FAMILY(name) {"easeIn" #name, QEasingCurve::In##name}, {"easeOut" #name, QEasingCurve::Out##name}, {"easeInOut" #name, QEasingCurve::InOut##name},
    FAMILY(Sine) FAMILY(Quad) FAMILY(Cubic) FAMILY(Quart) FAMILY(Quint)
    FAMILY(Expo) FAMILY(Circ) FAMILY(Back) FAMILY(Elastic) FAMILY(Bounce)
#undef FAMILY
};
inline QEasingCurve::Type curveType(const QString &name) {
    for (const auto &curve : curves) {
        if (name == QLatin1String(curve.name)) return curve.type;
    }
    return QEasingCurve::OutCubic;
}
inline QString curveName(const QString &name) {
    for (const auto &curve : curves) {
        if (name == QLatin1String(curve.name)) return name;
    }
    return QStringLiteral("easeOutCubic");
}
struct Settings {
    int clickTime = 150;
    int releaseTime = 720;
    QString clickEasing = QStringLiteral("easeOutBack");
    QString releaseEasing = QStringLiteral("easeOutElastic");
    void read(const KConfigGroup &group) {
        const Settings defaults;
        clickTime = std::clamp(group.readEntry("ClickTweenTime", defaults.clickTime), 0, 5000);
        releaseTime = std::clamp(group.readEntry("ReleaseTweenTime", defaults.releaseTime), 0, 5000);
        clickEasing = curveName(group.readEntry("ClickEasing", defaults.clickEasing));
        releaseEasing = curveName(group.readEntry("ReleaseEasing", defaults.releaseEasing));
    }
};
}
